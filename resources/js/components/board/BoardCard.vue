<script setup lang="ts">
import { computed } from 'vue'
import PriorityBadge from '@/components/badges/PriorityBadge.vue'
import TypeIcon from '@/components/icons/TypeIcon.vue'
import LabelChip from '@/components/badges/LabelChip.vue'
import BlockedIndicator from '@/components/badges/BlockedIndicator.vue'
import UserAvatar from '@/components/badges/UserAvatar.vue'
import AgentBadge from '@/components/badges/AgentBadge.vue'
import type { IssueRow } from '@/lib/types'

const props = defineProps<{ row: IssueRow; isHuman: (name: string | null) => boolean; done?: boolean; active?: boolean }>()
const emit = defineEmits<{ open: [id: string]; dragstart: [event: DragEvent, id: string] }>()

const agentAuthor = computed(() => props.row.created_by !== null && !props.isHuman(props.row.created_by))
</script>

<template>
  <article
    draggable="true"
    :data-issue-id="row.id"
    class="group/card cursor-grab rounded-lg border bg-card p-2.5 text-[13px] shadow-xs transition hover:border-foreground/20 active:cursor-grabbing"
    :class="{ 'ring-2 ring-ring/40': active }"
    @click="emit('open', row.id)"
    @dragstart="emit('dragstart', $event, row.id)"
  >
    <div class="flex items-center gap-1.5 text-[11.5px] text-muted-foreground">
      <span class="tabular-nums">{{ row.id }}</span>
      <BlockedIndicator v-if="row.blocked_by.length" :blocked-by="row.blocked_by" />
      <AgentBadge v-if="agentAuthor" :name="row.created_by ?? ''" compact />
      <span class="ml-auto"><UserAvatar :name="row.assignee" :agent="row.assignee !== null && !isHuman(row.assignee)" :size="18" /></span>
    </div>
    <p class="mt-1 line-clamp-3 leading-snug" :class="{ 'text-muted-foreground': done }">{{ row.title }}</p>
    <div class="mt-2 flex flex-wrap items-center gap-1.5">
      <span class="inline-flex h-5 items-center rounded border px-1"><PriorityBadge :priority="row.priority" /></span>
      <span v-if="row.type !== 'task'" class="inline-flex h-5 items-center rounded border px-1"><TypeIcon :type="row.type" :size="12" /></span>
      <LabelChip v-for="label in row.labels.slice(0, 3)" :key="label" :label="label" />
      <span v-if="row.progress" class="text-[11.5px] tabular-nums text-muted-foreground">{{ row.progress.done }}/{{ row.progress.total }}</span>
    </div>
  </article>
</template>
