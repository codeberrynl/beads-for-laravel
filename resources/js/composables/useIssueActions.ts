import { useQueryClient, type QueryClient } from '@tanstack/vue-query'
import { toast } from 'vue-sonner'
import { api, ApiError, type CreateFields, type RelationInput, type UpdateFields } from '@/lib/api'
import { noteHealth } from '@/composables/useBeads'
import { t } from '@/lib/i18n'
import type { Comment, Issue, IssueDetailPayload, IssueRow, SnapshotPayload } from '@/lib/types'

type Patch = <T extends IssueRow | Issue>(row: T) => T

/** Apply sent fields to a row or issue (for optimistic updates). */
export function applyFields(fields: UpdateFields): Patch {
  return (row) => {
    const next = { ...row }
    const writable = next as unknown as Record<string, unknown>

    for (const key of ['title', 'status', 'priority', 'type', 'assignee', 'parent'] as const) {
      if (key in fields) {
        writable[key] = fields[key] ?? null
      }
    }

    for (const key of ['description', 'design', 'acceptance_criteria', 'notes'] as const) {
      if (key in fields && key in next) {
        writable[key] = fields[key] ?? ''
      }
    }

    if (fields.labels) {
      const remove = new Set(fields.labels.remove ?? [])
      next.labels = [...new Set([...row.labels.filter((label) => !remove.has(label)), ...(fields.labels.add ?? [])])].sort()
    }

    next.updated_at = new Date().toISOString()

    return next
  }
}

function patchCaches(queryClient: QueryClient, id: string, patch: Patch): () => void {
  void queryClient.cancelQueries({ queryKey: ['snapshot'] })

  const snapshots = queryClient.getQueriesData<SnapshotPayload>({ queryKey: ['snapshot'] })
  const detail = queryClient.getQueryData<IssueDetailPayload>(['issue', id])

  for (const [key, data] of snapshots) {
    if (data) {
      queryClient.setQueryData<SnapshotPayload>(key, { ...data, issues: data.issues.map((row) => (row.id === id ? patch(row) : row)) })
    }
  }

  if (detail) {
    queryClient.setQueryData<IssueDetailPayload>(['issue', id], { ...detail, issue: patch(detail.issue) })
  }

  return () => {
    for (const [key, data] of snapshots) {
      queryClient.setQueryData(key, data)
    }

    if (detail) {
      queryClient.setQueryData(['issue', id], detail)
    }
  }
}

/** Store the fresh detail and refetch the snapshot when it moved on. */
function applyResponse(queryClient: QueryClient, payload: IssueDetailPayload): void {
  queryClient.setQueryData(['issue', payload.issue.id], payload)

  const stale = queryClient
    .getQueriesData<SnapshotPayload>({ queryKey: ['snapshot'] })
    .some(([, data]) => data !== undefined && data.hash !== payload.hash)

  if (stale) {
    void queryClient.invalidateQueries({ queryKey: ['snapshot'] })
  }

  for (const id of Object.keys(payload.issues)) {
    const cached = queryClient.getQueryData<IssueDetailPayload>(['issue', id])

    if (cached && cached.hash !== payload.hash) {
      void queryClient.invalidateQueries({ queryKey: ['issue', id] })
    }
  }
}

function failed(error: unknown, quiet = false): never {
  noteHealth(error)

  if (!quiet && !(error instanceof ApiError && error.isConflict)) {
    toast.error(t('toast.actionFailed', { message: error instanceof Error ? error.message : String(error) }))
  }

  throw error
}

export function useIssueActions() {
  const queryClient = useQueryClient()

  async function run(id: string, call: () => Promise<IssueDetailPayload>, patch?: Patch, quiet = false): Promise<IssueDetailPayload> {
    const rollback = patch ? patchCaches(queryClient, id, patch) : () => undefined

    try {
      const payload = await call()
      applyResponse(queryClient, payload)

      return payload
    } catch (error) {
      rollback()

      return failed(error, quiet)
    }
  }

  return {
    /** Per-field update; pickers are optimistic, a 409 is rethrown for the editor. */
    update: (id: string, fields: UpdateFields, options: { optimistic?: boolean; quiet?: boolean } = {}) =>
      run(id, () => api.update(id, fields), options.optimistic === false ? undefined : applyFields(fields), options.quiet),

    close: (id: string, reason?: string, force = false) =>
      run(id, () => api.close(id, reason, force), (row) => ({ ...row, status: 'closed', closed_at: new Date().toISOString() })),

    reopen: (id: string) => run(id, () => api.reopen(id), (row) => ({ ...row, status: 'open', closed_at: null })),

    addRelation: (id: string, relation: RelationInput) => run(id, () => api.addRelation(id, relation)),

    removeRelation: (id: string, relation: RelationInput) => run(id, () => api.removeRelation(id, relation)),

    comment: async (id: string, text: string, author: string) => {
      const optimistic: Comment = { id: `pending-${Date.now()}`, issue_id: id, author, text, created_at: new Date().toISOString() }

      return run(id, () => api.comment(id, text), (row) => ('comments' in row ? { ...row, comments: [...row.comments, optimistic] } : row))
    },

    create: async (fields: CreateFields): Promise<IssueDetailPayload> => {
      try {
        const payload = await api.create(fields)
        applyResponse(queryClient, payload)

        return payload
      } catch (error) {
        return failed(error)
      }
    },
  }
}
