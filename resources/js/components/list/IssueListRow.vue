<script setup lang="ts">
import { computed } from 'vue'
import type { IssueRow, StatusDefinition } from '@/lib/types'
import StatusIcon from '@/components/icons/StatusIcon.vue'
import PriorityBadge from '@/components/badges/PriorityBadge.vue'
import LabelChip from '@/components/badges/LabelChip.vue'
import BlockedIndicator from '@/components/badges/BlockedIndicator.vue'
import UserAvatar from '@/components/badges/UserAvatar.vue'
import AgentBadge from '@/components/badges/AgentBadge.vue'
import TypeIcon from '@/components/icons/TypeIcon.vue'
import { toneOf } from '@/lib/status'
import { shortDate } from '@/lib/time'
import { t } from '@/lib/i18n'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'

const props = defineProps<{
  row: IssueRow
  statuses: StatusDefinition[]
  active?: boolean
  parentTitle?: string | null
  isHuman: (author: string | null) => boolean
  showWaitingOn?: boolean
  titles?: Map<string, string>
}>()

const emit = defineEmits<{ open: [id: string]; focus: [id: string]; openBlocker: [id: string] }>()

const tone = computed(() => toneOf(props.row.status, props.statuses))
const agentAssignee = computed(() => props.row.assignee !== null && !props.isHuman(props.row.assignee))
const agentAuthor = computed(() => props.row.created_by !== null && !props.isHuman(props.row.created_by))
const visibleLabels = computed(() => props.row.labels.slice(0, 3))
</script>

<template>
  <div
    role="row"
    tabindex="-1"
    :data-issue-id="row.id"
    class="group/row flex h-8 cursor-default select-none items-center gap-2.5 border-b border-transparent px-4 text-[14px] hover:bg-row-hover"
    :class="{ 'bg-row-selected hover:bg-row-selected': active }"
    @click="emit('open', row.id)"
    @mouseenter="emit('focus', row.id)"
  >
    <PriorityBadge :priority="row.priority" />
    <span class="w-(--id-width) shrink-0 truncate text-[13px] tabular-nums text-muted-foreground">{{ row.id }}</span>
    <StatusIcon :tone="tone" />
    <TypeIcon v-if="row.type !== 'task'" :type="row.type" :size="13" />
    <span class="min-w-0 truncate" :class="{ 'text-muted-foreground line-through decoration-subtle/60': tone === 'done' }">{{ row.title }}</span>
    <BlockedIndicator v-if="row.blocked_by.length" :blocked-by="row.blocked_by" />
    <AgentBadge v-if="agentAuthor" :name="row.created_by ?? ''" compact />

    <span v-if="showWaitingOn && row.blocked_by.length" class="flex min-w-0 items-center gap-1 text-[13px] text-muted-foreground">
      <span class="shrink-0">{{ t('list.waitingOn') }}</span>
      <button
        v-for="blocker in row.blocked_by"
        :key="blocker"
        type="button"
        class="truncate rounded px-1 text-foreground/80 underline decoration-dotted underline-offset-2 hover:bg-accent hover:text-foreground"
        :title="titles?.get(blocker)"
        @click.stop="emit('openBlocker', blocker)"
      >
        {{ blocker }}
      </button>
    </span>

    <span class="ml-auto flex shrink-0 items-center gap-2 pl-2">
      <span v-if="parentTitle" class="hidden max-w-44 truncate rounded-full border px-2 text-[12.5px] leading-5 text-muted-foreground lg:inline">
        {{ parentTitle }}
      </span>
      <LabelChip v-for="label in visibleLabels" :key="label" :label="label" class="hidden md:inline-flex" />
      <span v-if="row.labels.length > 3" class="hidden text-[12px] text-subtle md:inline">+{{ row.labels.length - 3 }}</span>
      <span v-if="row.progress" class="text-[12.5px] tabular-nums text-muted-foreground">{{ row.progress.done }}/{{ row.progress.total }}</span>
      <Tooltip>
        <TooltipTrigger as-child>
          <span class="inline-flex"><UserAvatar :name="row.assignee" :agent="agentAssignee" /></span>
        </TooltipTrigger>
        <TooltipContent>{{ row.assignee ?? t('assignee.none') }}</TooltipContent>
      </Tooltip>
      <span class="w-12 text-right text-[13px] tabular-nums text-subtle">{{ shortDate(row.updated_at) }}</span>
    </span>
  </div>
</template>
