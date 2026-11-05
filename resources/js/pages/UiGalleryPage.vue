<script setup lang="ts">
import StatusIcon from '@/components/icons/StatusIcon.vue'
import PriorityBadge from '@/components/badges/PriorityBadge.vue'
import TypeIcon from '@/components/icons/TypeIcon.vue'
import LabelChip from '@/components/badges/LabelChip.vue'
import AgentBadge from '@/components/badges/AgentBadge.vue'
import BlockedIndicator from '@/components/badges/BlockedIndicator.vue'
import UserAvatar from '@/components/badges/UserAvatar.vue'
import ViewHeader from '@/components/ViewHeader.vue'
import { PRIORITIES, priorityLabel } from '@/lib/priority'
import { typeLabel } from '@/lib/types-meta'
import type { StatusTone } from '@/lib/status'

// Not linked from the UI: a gallery of every icon and badge variant.
const tones: StatusTone[] = ['frozen', 'active', 'wip', 'blocked', 'done', 'none']
const types = ['task', 'bug', 'feature', 'chore', 'epic', 'decision', 'spike', 'story', 'milestone', 'gate', 'incident']
</script>

<template>
  <div class="flex h-full flex-col">
    <ViewHeader title="UI gallery" />
    <div class="grid gap-6 overflow-auto p-6 text-[13px]">
      <section class="flex flex-wrap gap-4">
        <span v-for="tone in tones" :key="tone" class="flex items-center gap-1.5"><StatusIcon :tone="tone" /> {{ tone }}</span>
      </section>
      <section class="flex flex-wrap gap-4">
        <span v-for="priority in PRIORITIES" :key="priority" class="flex items-center gap-1.5"><PriorityBadge :priority="priority" /> {{ priorityLabel(priority) }}</span>
      </section>
      <section class="flex flex-wrap gap-4">
        <span v-for="type in types" :key="type" class="flex items-center gap-1.5"><TypeIcon :type="type" /> {{ typeLabel(type) }}</span>
      </section>
      <section class="flex flex-wrap items-center gap-2">
        <LabelChip v-for="label in ['frontend', 'backend', 'payments', 'checkout', 'a-much-longer-label-name']" :key="label" :label="label" />
        <AgentBadge name="claude-agent" />
        <AgentBadge name="claude-agent" compact />
        <BlockedIndicator :blocked-by="['demo-rif', 'demo-5l4']" />
        <UserAvatar name="Jurrid" />
        <UserAvatar name="claude-agent" agent />
        <UserAvatar :name="null" />
      </section>
    </div>
  </div>
</template>
