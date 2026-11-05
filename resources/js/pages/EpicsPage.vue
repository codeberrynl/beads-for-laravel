<script setup lang="ts">
import { computed, ref } from 'vue'
import { Boxes, ChevronRight, Plus } from '@lucide/vue'
import ViewHeader from '@/components/ViewHeader.vue'
import StatusIcon from '@/components/icons/StatusIcon.vue'
import PriorityBadge from '@/components/badges/PriorityBadge.vue'
import UserAvatar from '@/components/badges/UserAvatar.vue'
import IssueListRow from '@/components/list/IssueListRow.vue'
import { useSnapshot } from '@/composables/useBeads'
import { useWorkspace } from '@/composables/useWorkspace'
import { usePeek } from '@/composables/usePeek'
import { useCreateDialog } from '@/composables/useCreateDialog'
import { isDone, toneOf } from '@/lib/status'
import { sortRows } from '@/lib/filters'
import { t } from '@/lib/i18n'
import type { IssueRow } from '@/lib/types'

/** Epics with their progress (children in done ÷ all children, from the IssueGraph). */
const snapshot = useSnapshot()
const { statuses, isHuman } = useWorkspace()
const peek = usePeek()
const createDialog = useCreateDialog()

const expanded = ref(new Set<string>())

const epics = computed(() => {
  const all = sortRows(snapshot.rows.value.filter((row) => row.type === 'epic'), 'priority')

  // Open epics first, done ones last.
  return [...all.filter((row) => !isDone(row.status, statuses.value)), ...all.filter((row) => isDone(row.status, statuses.value))]
})

const childrenOf = computed(() => {
  const map = new Map<string, IssueRow[]>()
  for (const row of snapshot.rows.value) {
    if (row.parent) map.set(row.parent, [...(map.get(row.parent) ?? []), row])
  }
  for (const [key, rows] of map) map.set(key, sortRows(rows, 'priority'))
  return map
})

function percent(row: IssueRow): number {
  return row.progress && row.progress.total > 0 ? Math.round((row.progress.done / row.progress.total) * 100) : 0
}

function toggle(id: string) {
  const next = new Set(expanded.value)
  next.has(id) ? next.delete(id) : next.add(id)
  expanded.value = next
}
</script>

<template>
  <div class="flex h-full flex-col">
    <ViewHeader :title="t('views.epics')" :count="epics.length">
      <template #icon><Boxes class="size-4 text-muted-foreground" /></template>
    </ViewHeader>

    <div v-if="!epics.length && !snapshot.isPending.value" class="grid flex-1 place-items-center text-[13px] text-muted-foreground">{{ t('epics.empty') }}</div>

    <div v-else class="min-h-0 flex-1 overflow-y-auto">
      <div v-for="epic in epics" :key="epic.id" class="border-b">
        <div class="group/epic flex h-11 items-center gap-2.5 px-4 text-[13px] hover:bg-row-hover" @click="peek.open(epic.id)">
          <button
            type="button"
            class="grid size-5 place-items-center rounded text-muted-foreground hover:bg-accent hover:text-foreground"
            :aria-label="expanded.has(epic.id) ? t('epics.collapse') : t('epics.expand')"
            :aria-expanded="expanded.has(epic.id)"
            @click.stop="toggle(epic.id)"
          >
            <ChevronRight class="size-3.5 transition-transform" :class="{ 'rotate-90': expanded.has(epic.id) }" />
          </button>
          <PriorityBadge :priority="epic.priority" />
          <StatusIcon :tone="toneOf(epic.status, statuses)" />
          <span class="min-w-0 truncate font-medium">{{ epic.title }}</span>
          <span class="shrink-0 text-[12px] tabular-nums text-muted-foreground">{{ epic.id }}</span>

          <div class="ml-auto flex shrink-0 items-center gap-3">
            <div class="flex w-48 items-center gap-2" :title="epic.progress ? t('detail.progress', { ...epic.progress }) : t('epics.noChildren')">
              <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted" role="progressbar" :aria-valuenow="percent(epic)" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-status-done transition-all" :style="{ width: `${percent(epic)}%` }" />
              </div>
              <span class="w-14 text-right text-[12px] tabular-nums text-muted-foreground">
                {{ epic.progress ? t('epics.progress', { ...epic.progress }) : '–' }}
              </span>
            </div>
            <UserAvatar :name="epic.assignee" :agent="epic.assignee !== null && !isHuman(epic.assignee)" />
            <button
              type="button"
              class="grid size-6 place-items-center rounded text-muted-foreground opacity-0 hover:bg-accent hover:text-foreground group-hover/epic:opacity-100"
              :aria-label="t('epics.addSubIssue')"
              @click.stop="createDialog.open({ parent: epic.id })"
            >
              <Plus class="size-3.5" />
            </button>
          </div>
        </div>

        <div v-if="expanded.has(epic.id)" class="border-t bg-muted/20 pb-1 pl-8" style="--id-width: 7em">
          <IssueListRow
            v-for="child in childrenOf.get(epic.id) ?? []"
            :key="child.id"
            :row="child"
            :statuses="statuses"
            :is-human="isHuman"
            :active="peek.peekId.value === child.id"
            @open="peek.open"
          />
          <div v-if="!(childrenOf.get(epic.id) ?? []).length" class="px-4 py-2 text-[12px] text-subtle">{{ t('epics.noChildren') }}</div>
          <button type="button" class="ml-4 mt-1 inline-flex h-7 items-center gap-1.5 text-[12px] text-muted-foreground hover:text-foreground" @click="createDialog.open({ parent: epic.id })">
            <Plus class="size-3.5" /> {{ t('epics.addSubIssue') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
