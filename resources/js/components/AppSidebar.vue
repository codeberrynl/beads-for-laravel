<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Archive, Ban, Boxes, ChevronDown, CircleDot, Layers, Monitor, Moon, Search, SquareKanban, SquarePen, Sun, UserRound, Zap } from '@lucide/vue'
import { t } from '@/lib/i18n'
import { useTheme, type ThemeChoice } from '@/composables/useTheme'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { Kbd } from '@/components/ui/kbd'

const props = defineProps<{
  workspace: string
  actor: string
  counts?: Partial<Record<string, number>>
}>()

const emit = defineEmits<{ search: []; create: [] }>()

const { choice, setTheme } = useTheme()

const issueLinks = [
  { to: '/issues', label: 'nav.all', icon: Layers, count: 'all' },
  { to: '/issues/active', label: 'nav.active', icon: CircleDot, count: 'active' },
  { to: '/issues/backlog', label: 'nav.backlog', icon: Archive, count: 'backlog' },
]

const viewLinks = [
  { to: '/my-issues', label: 'nav.myIssues', icon: UserRound, count: 'mine' },
  { to: '/ready', label: 'nav.ready', icon: Zap, count: 'ready' },
  { to: '/blocked', label: 'nav.blocked', icon: Ban, count: 'blocked' },
  { to: '/board', label: 'nav.board', icon: SquareKanban },
  { to: '/epics', label: 'nav.epics', icon: Boxes, count: 'epics' },
]

const themes: { value: ThemeChoice; icon: typeof Sun }[] = [
  { value: 'system', icon: Monitor },
  { value: 'light', icon: Sun },
  { value: 'dark', icon: Moon },
]

const initial = computed(() => (props.workspace.trim()[0] ?? 'B').toUpperCase())
const actorInitials = computed(() =>
  props.actor
    .split(/[\s._-]+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('') || '?',
)

function countFor(key?: string): number | undefined {
  return key ? props.counts?.[key] : undefined
}
</script>

<template>
  <aside class="flex h-full w-[232px] shrink-0 flex-col border-r bg-sidebar text-sidebar-foreground">
    <div class="flex h-12 items-center gap-2 px-3">
      <div class="flex min-w-0 flex-1 items-center gap-2 rounded-md px-1.5 py-1">
        <span class="grid size-5 shrink-0 place-items-center rounded-[5px] bg-primary text-[11px] font-semibold text-primary-foreground">
          {{ initial }}
        </span>
        <span class="truncate text-[13px] font-medium text-foreground">{{ workspace }}</span>
      </div>
      <Tooltip>
        <TooltipTrigger as-child>
          <button
            type="button"
            class="grid size-7 place-items-center rounded-md text-muted-foreground hover:bg-sidebar-accent hover:text-foreground"
            :aria-label="t('nav.search')"
            @click="emit('search')"
          >
            <Search class="size-4" />
          </button>
        </TooltipTrigger>
        <TooltipContent side="bottom">{{ t('nav.search') }} <Kbd class="ml-1">⌘K</Kbd></TooltipContent>
      </Tooltip>
      <Tooltip>
        <TooltipTrigger as-child>
          <button
            type="button"
            class="grid size-7 place-items-center rounded-md border bg-background text-foreground shadow-xs hover:bg-accent"
            :aria-label="t('nav.newIssue')"
            @click="emit('create')"
          >
            <SquarePen class="size-[15px]" />
          </button>
        </TooltipTrigger>
        <TooltipContent side="bottom">{{ t('nav.newIssue') }} <Kbd class="ml-1">C</Kbd></TooltipContent>
      </Tooltip>
    </div>

    <nav class="flex-1 space-y-4 overflow-y-auto px-2 py-2">
      <div>
        <div class="px-2 pb-1 text-[11px] font-medium text-subtle">{{ t('nav.issues') }}</div>
        <RouterLink
          v-for="link in issueLinks"
          :key="link.to"
          :to="link.to"
          class="group flex h-7 items-center gap-2 rounded-md px-2 text-[13px] hover:bg-sidebar-accent hover:text-foreground"
          exact-active-class="bg-sidebar-accent text-foreground font-medium"
        >
          <component :is="link.icon" class="size-4 shrink-0 text-muted-foreground group-hover:text-foreground" />
          <span class="flex-1 truncate">{{ t(link.label) }}</span>
          <span v-if="countFor(link.count) !== undefined" class="text-[11px] tabular-nums text-subtle">{{ countFor(link.count) }}</span>
        </RouterLink>
      </div>

      <div>
        <div class="px-2 pb-1 text-[11px] font-medium text-subtle">{{ t('nav.views') }}</div>
        <RouterLink
          v-for="link in viewLinks"
          :key="link.to"
          :to="link.to"
          class="group flex h-7 items-center gap-2 rounded-md px-2 text-[13px] hover:bg-sidebar-accent hover:text-foreground"
          active-class="bg-sidebar-accent text-foreground font-medium"
        >
          <component :is="link.icon" class="size-4 shrink-0 text-muted-foreground group-hover:text-foreground" />
          <span class="flex-1 truncate">{{ t(link.label) }}</span>
          <span v-if="countFor(link.count) !== undefined" class="text-[11px] tabular-nums text-subtle">{{ countFor(link.count) }}</span>
        </RouterLink>
      </div>
    </nav>

    <div class="border-t p-2">
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <button type="button" class="flex h-8 w-full items-center gap-2 rounded-md px-2 text-left text-[13px] hover:bg-sidebar-accent">
            <span class="grid size-5 shrink-0 place-items-center rounded-full bg-muted text-[10px] font-semibold text-foreground">{{ actorInitials }}</span>
            <span class="min-w-0 flex-1 truncate text-foreground">{{ actor }}</span>
            <ChevronDown class="size-3.5 text-muted-foreground" />
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent side="top" align="start" class="w-56">
          <DropdownMenuLabel class="text-xs font-normal text-muted-foreground">
            {{ t('user.signedInAs', { name: actor }) }}
            <span class="mt-0.5 block text-[11px] text-subtle">{{ t('user.actorHint') }}</span>
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          <DropdownMenuLabel class="text-xs text-muted-foreground">{{ t('theme.label') }}</DropdownMenuLabel>
          <DropdownMenuRadioGroup :model-value="choice" @update:model-value="(value) => setTheme(value as ThemeChoice)">
            <DropdownMenuRadioItem v-for="theme in themes" :key="theme.value" :value="theme.value" class="gap-2">
              <component :is="theme.icon" class="size-3.5 text-muted-foreground" />
              {{ t(`theme.${theme.value}`) }}
            </DropdownMenuRadioItem>
          </DropdownMenuRadioGroup>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  </aside>
</template>
