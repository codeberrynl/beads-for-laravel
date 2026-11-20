<script setup lang="ts">
import { computed, nextTick, ref, watch, type Component } from 'vue'
import { useRouter } from 'vue-router'
import { Archive, Ban, Boxes, CircleDot, CornerDownLeft, Layers, Palette, SquareKanban, SquarePen, UserRound, Zap } from '@lucide/vue'
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog'
import StatusIcon from '@/components/icons/StatusIcon.vue'
import PriorityIcon from '@/components/icons/PriorityIcon.vue'
import { Kbd } from '@/components/ui/kbd'
import { useSnapshot } from '@/composables/useBeads'
import { useWorkspace } from '@/composables/useWorkspace'
import { usePalette } from '@/composables/usePalette'
import { useCreateDialog } from '@/composables/useCreateDialog'
import { usePeek } from '@/composables/usePeek'
import { useTheme } from '@/composables/useTheme'
import { matchesSearch, parseSearch } from '@/lib/filters'
import { toneOf } from '@/lib/status'
import { t } from '@/lib/i18n'
import type { IssueRow } from '@/lib/types'

interface Item {
  id: string
  group: 'actions' | 'navigation' | 'issues'
  label: string
  icon?: Component
  row?: IssueRow
  hint?: string
  run: () => void
}

const palette = usePalette()
const router = useRouter()
const snapshot = useSnapshot()
const { statuses } = useWorkspace()
const createDialog = useCreateDialog()
const peek = usePeek()
const theme = useTheme()

const query = ref('')
const index = ref(0)
const input = ref<HTMLInputElement | null>(null)
const listbox = ref<HTMLElement | null>(null)

const go = (path: string) => () => void router.push(path)

const fixed = computed<Item[]>(() => [
  { id: 'create', group: 'actions', label: t('palette.createIssue'), icon: SquarePen, hint: 'C', run: () => createDialog.open() },
  { id: 'theme', group: 'actions', label: `${t('palette.toggleTheme')} (${t(`theme.${theme.choice.value}`)})`, icon: Palette, run: () => { theme.cycle(); palette.show() } },
  { id: 'nav-all', group: 'navigation', label: t('nav.all'), icon: Layers, run: go('/issues') },
  { id: 'nav-active', group: 'navigation', label: t('nav.active'), icon: CircleDot, run: go('/issues/active') },
  { id: 'nav-backlog', group: 'navigation', label: t('nav.backlog'), icon: Archive, run: go('/issues/backlog') },
  { id: 'nav-mine', group: 'navigation', label: t('nav.myIssues'), icon: UserRound, run: go('/my-issues') },
  { id: 'nav-ready', group: 'navigation', label: t('nav.ready'), icon: Zap, run: go('/ready') },
  { id: 'nav-blocked', group: 'navigation', label: t('nav.blocked'), icon: Ban, run: go('/blocked') },
  { id: 'nav-board', group: 'navigation', label: t('nav.board'), icon: SquareKanban, run: go('/board') },
  { id: 'nav-epics', group: 'navigation', label: t('nav.epics'), icon: Boxes, run: go('/epics') },
])

const items = computed<Item[]>(() => {
  const q = query.value.trim()
  const { text, priorities } = parseSearch(q)

  const commands = q === '' ? fixed.value : priorities.length ? [] : fixed.value.filter((item) => item.label.toLowerCase().includes(text))

  const rows = q === ''
    ? [...snapshot.rows.value].sort((a, b) => Date.parse(b.updated_at ?? '') - Date.parse(a.updated_at ?? '')).slice(0, 8)
    : snapshot.rows.value.filter((row) => matchesSearch(row, q)).sort((a, b) => a.priority - b.priority).slice(0, 50)

  const issues: Item[] = rows.map((row) => ({ id: `issue-${row.id}`, group: 'issues', label: row.title, row, run: () => peek.open(row.id) }))

  return q === '' ? [...commands.filter((item) => item.group === 'actions'), ...issues, ...commands.filter((item) => item.group === 'navigation')] : [...issues, ...commands]
})

const groups = computed(() => {
  const order: Item['group'][] = []
  for (const item of items.value) if (!order.includes(item.group)) order.push(item.group)
  return order.map((group) => ({ group, items: items.value.filter((item) => item.group === group) }))
})

watch(query, () => (index.value = 0))

watch(
  () => palette.open.value,
  async (open) => {
    if (open) {
      query.value = ''
      index.value = 0
      await nextTick()
      input.value?.focus()
    }
  },
)

function move(delta: number) {
  if (!items.value.length) return
  index.value = (index.value + delta + items.value.length) % items.value.length
  void nextTick(() => listbox.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' }))
}

function choose(item: Item | undefined) {
  if (!item) return
  palette.hide()
  item.run()
}
</script>

<template>
  <Dialog :open="palette.open.value" @update:open="(value) => (palette.open.value = value)">
    <DialogContent class="top-[18%] max-w-xl translate-y-0 gap-0 overflow-hidden p-0 sm:max-w-xl" :show-close-button="false">
      <DialogTitle class="sr-only">{{ t('palette.placeholder') }}</DialogTitle>
      <DialogDescription class="sr-only">{{ t('palette.placeholder') }}</DialogDescription>
      <input
        ref="input"
        v-model="query"
        role="combobox"
        aria-expanded="true"
        aria-controls="beads-palette-list"
        :aria-activedescendant="items[index] ? `palette-${items[index]!.id}` : undefined"
        :placeholder="t('palette.placeholder')"
        class="h-12 w-full border-b bg-transparent px-4 text-[15px] outline-none placeholder:text-subtle"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
        @keydown.enter.prevent="choose(items[index])"
      />
      <div id="beads-palette-list" ref="listbox" role="listbox" class="max-h-[min(60vh,420px)] overflow-y-auto p-1.5">
        <div v-if="!items.length" class="px-3 py-6 text-center text-[14px] text-muted-foreground">{{ t('app.noResults') }}</div>
        <div v-for="section in groups" :key="section.group" class="mb-1">
          <div class="px-2.5 pb-1 pt-2 text-[12px] font-medium text-subtle">{{ t(`palette.${section.group}`) }}</div>
          <div
            v-for="item in section.items"
            :id="`palette-${item.id}`"
            :key="item.id"
            role="option"
            :aria-selected="items[index]?.id === item.id"
            :data-active="items[index]?.id === item.id"
            class="flex h-9 cursor-default items-center gap-2.5 rounded-md px-2.5 text-[14px] data-[active=true]:bg-accent"
            @mousemove="index = items.indexOf(item)"
            @click="choose(item)"
          >
            <template v-if="item.row">
              <PriorityIcon :priority="item.row.priority" />
              <StatusIcon :tone="toneOf(item.row.status, statuses)" />
              <span class="shrink-0 text-[13px] tabular-nums text-muted-foreground">{{ item.row.id }}</span>
              <span class="truncate">{{ item.label }}</span>
            </template>
            <template v-else>
              <component :is="item.icon" class="size-4 text-muted-foreground" />
              <span class="truncate">{{ item.label }}</span>
              <Kbd v-if="item.hint" class="ml-auto">{{ item.hint }}</Kbd>
            </template>
            <CornerDownLeft v-if="items[index]?.id === item.id" class="ml-auto size-3.5 shrink-0 text-subtle" :class="{ 'ml-2': item.hint }" />
          </div>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
