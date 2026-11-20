<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { ChevronRight, Plus } from '@lucide/vue'
import type { IssueRow, StatusDefinition } from '@/lib/types'
import type { IssueGroup } from '@/lib/filters'
import IssueListRow from '@/components/list/IssueListRow.vue'
import StatusIcon from '@/components/icons/StatusIcon.vue'
import PriorityIcon from '@/components/icons/PriorityIcon.vue'
import TypeIcon from '@/components/icons/TypeIcon.vue'
import UserAvatar from '@/components/badges/UserAvatar.vue'
import { t } from '@/lib/i18n'

/**
 * Dense, grouped rows. `activeId` is the keyboard/hover cursor. The rows
 * carry their ID, so a later multi-select can build on this component.
 */
const props = defineProps<{
  groups: IssueGroup[]
  statuses: StatusDefinition[]
  activeId: string | null
  byId: Map<string, IssueRow>
  isHuman: (author: string | null) => boolean
  showWaitingOn?: boolean
}>()

const emit = defineEmits<{
  open: [id: string]
  'update:activeId': [id: string | null]
  create: [group: IssueGroup]
}>()

const collapsed = ref(new Set<string>())
const root = ref<HTMLElement | null>(null)

const titles = computed(() => new Map([...props.byId.values()].map((row) => [row.id, row.title])))

/** One width for the ID column: the longest visible ID. */
const idWidth = computed(() => `${Math.max(6, ...props.groups.flatMap((group) => group.rows.map((row) => row.id.length))) * 0.62 + 0.5}em`)

function toggle(key: string) {
  const next = new Set(collapsed.value)
  next.has(key) ? next.delete(key) : next.add(key)
  collapsed.value = next
}

/** The visible rows in order, for J/K navigation. */
const visibleIds = computed(() =>
  props.groups.flatMap((group) => (collapsed.value.has(group.key) ? [] : group.rows.map((row) => row.id))),
)

defineExpose({ visibleIds })

watch(
  () => props.activeId,
  async (id) => {
    if (!id) return
    await nextTick()
    root.value?.querySelector(`[data-issue-id="${CSS.escape(id)}"]`)?.scrollIntoView({ block: 'nearest' })
  },
)
</script>

<template>
  <div ref="root" role="grid" class="min-h-0 flex-1 overflow-y-auto" :style="{ '--id-width': idWidth }">
    <template v-for="group in groups" :key="group.key">
      <div
        v-if="group.label !== ''"
        class="group/head sticky top-0 z-10 flex h-9 items-center gap-2 border-b bg-muted/70 px-4 text-[14px] backdrop-blur"
      >
        <button type="button" class="flex min-w-0 items-center gap-2" @click="toggle(group.key)">
          <ChevronRight class="size-3.5 text-muted-foreground transition-transform" :class="{ 'rotate-90': !collapsed.has(group.key) }" />
          <StatusIcon v-if="group.tone" :tone="group.tone" />
          <PriorityIcon v-else-if="group.priority !== undefined" :priority="group.priority" />
          <TypeIcon v-else-if="group.type" :type="group.type" />
          <UserAvatar v-else-if="group.assignee !== undefined" :name="group.assignee" :agent="group.assignee !== null && !isHuman(group.assignee)" :size="16" />
          <span class="truncate font-medium">{{ group.label }}</span>
          <span class="text-[13px] tabular-nums text-muted-foreground">{{ group.rows.length }}</span>
        </button>
        <button
          type="button"
          class="ml-auto grid size-6 place-items-center rounded text-muted-foreground opacity-0 hover:bg-accent hover:text-foreground group-hover/head:opacity-100"
          :aria-label="t('list.addToGroup', { group: group.label })"
          @click="emit('create', group)"
        >
          <Plus class="size-3.5" />
        </button>
      </div>
      <template v-if="!collapsed.has(group.key)">
        <IssueListRow
          v-for="row in group.rows"
          :key="row.id"
          :row="row"
          :statuses="statuses"
          :active="row.id === activeId"
          :parent-title="row.parent ? byId.get(row.parent)?.title ?? null : null"
          :is-human="isHuman"
          :show-waiting-on="showWaitingOn"
          :titles="titles"
          @open="emit('open', $event)"
          @focus="emit('update:activeId', $event)"
          @open-blocker="emit('open', $event)"
        />
      </template>
    </template>
    <slot name="footer" />
  </div>
</template>
