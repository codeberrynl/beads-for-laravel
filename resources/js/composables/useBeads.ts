import { computed, ref, watch, type Ref } from 'vue'
import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/vue-query'
import { api, ApiError } from '@/lib/api'
import { config } from '@/lib/config'
import type { Health, IssueDetailPayload, IssueRow, SnapshotPayload } from '@/lib/types'

/** Set when any API call answers with a 503 health payload. */
export const healthOverride = ref<Health | null>(null)

/** "Show older closed": the snapshot variant with every closed issue. */
export const showAllClosed = ref(false)

const etags = new Map<string, string>()

export function noteHealth(error: unknown): void {
  if (error instanceof ApiError && error.isUnhealthy && error.body.health) {
    healthOverride.value = error.body.health
  }
}

export function useBootstrap() {
  return useQuery({
    queryKey: ['bootstrap'],
    queryFn: api.bootstrap,
    staleTime: 30_000,
    refetchInterval: 60_000,
    refetchOnWindowFocus: true,
  })
}

export function snapshotKey(all: boolean) {
  return ['snapshot', all ? 'all' : 'window'] as const
}

/**
 * The snapshot, polled every poll_interval seconds with If-None-Match, so
 * an unchanged workspace costs a 304. Polling pauses in hidden tabs.
 */
export function useSnapshot() {
  const queryClient = useQueryClient()

  const query = useQuery({
    queryKey: computed(() => snapshotKey(showAllClosed.value)),
    queryFn: async ({ queryKey }): Promise<SnapshotPayload> => {
      const variant = queryKey[1]
      const cached = queryClient.getQueryData<SnapshotPayload>(queryKey)

      try {
        const response = await api.snapshot(variant === 'all', cached ? etags.get(variant) ?? null : null)
        healthOverride.value = null

        if (response.status === 304 && cached) {
          return cached
        }

        if (response.etag) {
          etags.set(variant, response.etag)
        }

        return response.data as SnapshotPayload
      } catch (error) {
        noteHealth(error)
        throw error
      }
    },
    refetchInterval: config.pollInterval * 1000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: true,
    placeholderData: keepPreviousData,
    staleTime: 0,
  })

  const rows = computed<IssueRow[]>(() => query.data.value?.issues ?? [])
  const byId = computed(() => new Map(rows.value.map((row) => [row.id, row])))

  return { ...query, rows, byId, showAllClosed }
}

/**
 * The full detail of one issue. It is refetched when the snapshot hash
 * moves on, so outside changes show up in an open detail as well.
 */
export function useIssueDetail(id: Ref<string | null | undefined>) {
  const snapshot = useSnapshot()

  const query = useQuery({
    queryKey: computed(() => ['issue', id.value]),
    queryFn: async (): Promise<IssueDetailPayload> => {
      try {
        return await api.issue(id.value as string)
      } catch (error) {
        noteHealth(error)
        throw error
      }
    },
    enabled: computed(() => Boolean(id.value)),
    retry: false,
  })

  watch(
    () => snapshot.data.value?.hash,
    (hash) => {
      if (hash && query.data.value && query.data.value.hash !== hash && !query.isFetching.value) {
        void query.refetch()
      }
    },
  )

  return query
}
