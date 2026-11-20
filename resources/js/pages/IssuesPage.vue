<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Archive, Ban, CircleDot, Layers, UserRound, Zap } from '@lucide/vue'
import ViewHeader from '@/components/ViewHeader.vue'
import IssueList from '@/components/list/IssueList.vue'
import FilterMenu from '@/components/list/FilterMenu.vue'
import FilterChips from '@/components/list/FilterChips.vue'
import DisplayMenu from '@/components/list/DisplayMenu.vue'
import SearchBox from '@/components/list/SearchBox.vue'
import { useSnapshot, showAllClosed } from '@/composables/useBeads'
import { useWorkspace } from '@/composables/useWorkspace'
import { useListState } from '@/composables/useListState'
import { usePeek } from '@/composables/usePeek'
import { useCreateDialog } from '@/composables/useCreateDialog'
import { applyFilters, groupRows, hasFilters, sortRows, type IssueGroup } from '@/lib/filters'
import { inView, type ListView } from '@/lib/issues'
import { t } from '@/lib/i18n'
import { useShortcut } from '@/composables/useShortcut'

const route = useRoute()
const router = useRouter()
const snapshot = useSnapshot()
const { statuses, selectableTypes, types, actor, isHuman } = useWorkspace()
const { filters, display, setFilters, setDisplay, clearFilters, toggle } = useListState()
const peek = usePeek()
const createDialog = useCreateDialog()

const view = computed<ListView>(() => route.meta.view ?? 'all')

const meta: Record<ListView, { title: string; icon: unknown; hint?: string }> = {
  all: { title: 'views.all', icon: Layers },
  active: { title: 'views.active', icon: CircleDot },
  backlog: { title: 'views.backlog', icon: Archive },
  mine: { title: 'views.myIssues', icon: UserRound },
  ready: { title: 'views.ready', icon: Zap, hint: 'views.readyHint' },
  blocked: { title: 'views.blocked', icon: Ban, hint: 'views.blockedHint' },
}

const context = computed(() => ({ actor: actor.value, statuses: statuses.value }))
const viewRows = computed(() => snapshot.rows.value.filter((row) => inView(row, view.value, context.value)))
const filteredRows = computed(() => sortRows(applyFilters(viewRows.value, filters.value), display.value.sort))
const groups = computed(() => groupRows(filteredRows.value, display.value.group, statuses.value, types.value))

const activeId = ref<string | null>(null)
const list = ref<InstanceType<typeof IssueList> | null>(null)
const search = ref<InstanceType<typeof SearchBox> | null>(null)

// "Show older closed" is part of the URL as well (?closed=all).
watch(
  () => route.query.closed,
  (closed) => {
    showAllClosed.value = closed === 'all'
  },
  { immediate: true },
)

function toggleOlderClosed() {
  const { closed: _closed, ...query } = route.query
  void router.replace({ query: showAllClosed.value ? query : { ...query, closed: 'all' } })
}

const hiddenClosed = computed(() => snapshot.data.value?.hidden_closed ?? 0)

/** Ready, Blocked and Backlog never contain closed issues. */
const showsClosed = computed(() => ['all', 'active', 'mine'].includes(view.value))

function createIn(group: IssueGroup) {
  const prefill = display.value.group === 'status' ? { status: group.key }
    : display.value.group === 'priority' ? { priority: Number(group.key) }
    : display.value.group === 'type' ? { type: group.key }
    : display.value.group === 'assignee' ? { assignee: group.assignee ?? null }
    : {}

  createDialog.open(prefill)
}

function step(delta: number) {
  const ids = list.value?.visibleIds ?? []
  if (!ids.length) return
  const current = activeId.value ? ids.indexOf(activeId.value) : -1
  const next = current === -1 ? (delta > 0 ? 0 : ids.length - 1) : Math.min(ids.length - 1, Math.max(0, current + delta))
  activeId.value = ids[next] ?? null
  // With the peek open, J/K walk through the issues in it.
  if (peek.peekId.value && activeId.value) peek.open(activeId.value)
}

useShortcut(['j', 'arrowdown'], () => step(1))
useShortcut(['k', 'arrowup'], () => step(-1))
useShortcut('enter', () => {
  if (!activeId.value) return false
  peek.open(activeId.value)
})
useShortcut('/', () => search.value?.focus())

// Keep the cursor on the issue shown in the peek.
watch(() => peek.peekId.value, (id) => {
  if (id) activeId.value = id
})

defineExpose({ list, search, activeId })
</script>

<template>
  <div class="flex h-full flex-col">
    <ViewHeader :title="t(meta[view].title)" :count="filteredRows.length">
      <template #icon><component :is="meta[view].icon" class="size-4 text-muted-foreground" /></template>
      <template #actions>
        <SearchBox ref="search" :model-value="filters.q" @update:model-value="(q) => setFilters({ q })" />
        <FilterMenu :filters="filters" :rows="viewRows" :statuses="statuses" :types="selectableTypes" :by-id="snapshot.byId.value" @toggle="(key, value) => toggle(key, value as never)" />
        <DisplayMenu :display="display" @change="setDisplay" />
      </template>
    </ViewHeader>

    <FilterChips :filters="filters" :by-id="snapshot.byId.value" @remove="(key) => setFilters({ [key]: [] })" @clear="clearFilters" />

    <div v-if="meta[view].hint" class="border-b px-4 py-2 text-[13px] text-muted-foreground">{{ t(meta[view].hint!) }}</div>

    <div v-if="snapshot.isPending.value" class="p-6 text-[14px] text-muted-foreground">{{ t('app.loading') }}</div>

    <div v-else-if="!filteredRows.length" class="grid flex-1 place-items-center p-6 text-center">
      <div>
        <p class="text-[14px] text-muted-foreground">{{ hasFilters(filters) ? t('list.emptyFiltered') : t('list.empty') }}</p>
        <button v-if="hasFilters(filters)" type="button" class="mt-2 text-[14px] text-primary hover:underline" @click="clearFilters">{{ t('list.clearFilters') }}</button>
      </div>
    </div>

    <IssueList
      v-else
      ref="list"
      v-model:active-id="activeId"
      :groups="groups"
      :statuses="statuses"
      :by-id="snapshot.byId.value"
      :is-human="isHuman"
      :show-waiting-on="view === 'blocked'"
      @open="peek.open"
      @create="createIn"
    >
      <template #footer>
        <div v-if="showsClosed && (hiddenClosed > 0 || showAllClosed)" class="flex items-center gap-2 px-4 py-3 text-[13px] text-muted-foreground">
          <span v-if="hiddenClosed > 0">{{ t('list.olderClosedHidden', { count: hiddenClosed }) }}</span>
          <button type="button" class="text-primary hover:underline" @click="toggleOlderClosed">
            {{ showAllClosed ? t('list.hideOlderClosed') : t('list.showOlderClosed') }}
          </button>
        </div>
      </template>
    </IssueList>
  </div>
</template>
