<script setup lang="ts">
import { computed, ref } from 'vue'
import { Plus, SquareKanban } from '@lucide/vue'
import ViewHeader from '@/components/ViewHeader.vue'
import BoardCard from '@/components/board/BoardCard.vue'
import StatusIcon from '@/components/icons/StatusIcon.vue'
import FilterMenu from '@/components/list/FilterMenu.vue'
import FilterChips from '@/components/list/FilterChips.vue'
import DisplayMenu from '@/components/list/DisplayMenu.vue'
import SearchBox from '@/components/list/SearchBox.vue'
import { useSnapshot } from '@/composables/useBeads'
import { useWorkspace } from '@/composables/useWorkspace'
import { useListState } from '@/composables/useListState'
import { useIssueActions } from '@/composables/useIssueActions'
import { usePeek } from '@/composables/usePeek'
import { useCreateDialog } from '@/composables/useCreateDialog'
import { useShortcut } from '@/composables/useShortcut'
import { applyFilters, sortRows } from '@/lib/filters'
import { isDone, statusLabel, toneOf } from '@/lib/status'
import { t } from '@/lib/i18n'
import type { IssueRow } from '@/lib/types'

/**
 * One column per bd status, in category order. Dragging a card to another
 * column changes its status (or closes it in a done column); there is no
 * manual order within a column (sorting comes from the display options).
 */
const snapshot = useSnapshot()
const { statuses, selectableTypes, isHuman } = useWorkspace()
const { filters, display, setFilters, setDisplay, clearFilters, toggle } = useListState()
const actions = useIssueActions()
const peek = usePeek()
const createDialog = useCreateDialog()
const search = ref<InstanceType<typeof SearchBox> | null>(null)

const rows = computed(() => sortRows(applyFilters(snapshot.rows.value, filters.value), display.value.sort))

/** Niche statuses (hooked, pinned) only get a column when they hold issues. */
const columns = computed(() =>
  statuses.value
    .map((status) => ({ status, rows: rows.value.filter((row) => row.status === status.name) }))
    .filter((column) => column.rows.length > 0 || !['hooked', 'pinned'].includes(column.status.name)),
)

const dragging = ref<string | null>(null)
const over = ref<string | null>(null)

function onDragStart(event: DragEvent, id: string) {
  dragging.value = id
  event.dataTransfer?.setData('text/plain', id)
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

function onDrop(event: DragEvent, status: string) {
  const id = event.dataTransfer?.getData('text/plain') || dragging.value
  over.value = null
  dragging.value = null

  const row: IssueRow | undefined = id ? snapshot.byId.value.get(id) : undefined
  if (!row || row.status === status) return

  const write = status === 'closed' ? actions.close(row.id) : actions.update(row.id, { status })

  // A refusal rolls the card back and shows bd's message.
  void write.catch(() => undefined)
}

useShortcut('/', () => search.value?.focus())
</script>

<template>
  <div class="flex h-full flex-col">
    <ViewHeader :title="t('views.board')" :count="rows.length">
      <template #icon><SquareKanban class="size-4 text-muted-foreground" /></template>
      <template #actions>
        <SearchBox ref="search" :model-value="filters.q" @update:model-value="(q) => setFilters({ q })" />
        <FilterMenu :filters="filters" :rows="snapshot.rows.value" :statuses="statuses" :types="selectableTypes" :by-id="snapshot.byId.value" @toggle="(key, value) => toggle(key, value as never)" />
        <DisplayMenu :display="display" :groupable="false" @change="setDisplay" />
      </template>
    </ViewHeader>
    <FilterChips :filters="filters" :by-id="snapshot.byId.value" @remove="(key) => setFilters({ [key]: [] })" @clear="clearFilters" />

    <div class="flex min-h-0 flex-1 gap-3 overflow-x-auto p-3">
      <section
        v-for="column in columns"
        :key="column.status.name"
        class="flex w-72 shrink-0 flex-col rounded-lg bg-muted/40 transition-colors"
        :class="{ 'bg-accent/70 ring-1 ring-ring/40': over === column.status.name }"
        @dragover.prevent="over = column.status.name"
        @dragleave="over = over === column.status.name ? null : over"
        @drop.prevent="onDrop($event, column.status.name)"
      >
        <header class="group/col flex h-9 shrink-0 items-center gap-2 px-3 text-[14px]">
          <StatusIcon :tone="toneOf(column.status.name, statuses)" />
          <span class="font-medium">{{ statusLabel(column.status.name) }}</span>
          <span class="text-[13px] tabular-nums text-muted-foreground">{{ column.rows.length }}</span>
          <button
            type="button"
            class="ml-auto grid size-6 place-items-center rounded text-muted-foreground opacity-0 hover:bg-accent hover:text-foreground group-hover/col:opacity-100"
            :aria-label="t('list.addToGroup', { group: statusLabel(column.status.name) })"
            @click="createDialog.open({ status: column.status.name })"
          >
            <Plus class="size-3.5" />
          </button>
        </header>
        <div class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto px-2 pb-2">
          <BoardCard
            v-for="row in column.rows"
            :key="row.id"
            :row="row"
            :is-human="isHuman"
            :done="isDone(row.status, statuses)"
            :active="peek.peekId.value === row.id"
            @open="peek.open"
            @dragstart="onDragStart"
          />
          <p v-if="!column.rows.length" class="px-2 py-6 text-center text-[13px] text-subtle">
            {{ isDone(column.status.name, statuses) ? t('board.dropToClose') : t('board.empty') }}
          </p>
        </div>
      </section>
    </div>
  </div>
</template>
