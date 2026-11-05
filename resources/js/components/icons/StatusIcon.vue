<script setup lang="ts">
import { computed } from 'vue'
import type { StatusTone } from '@/lib/status'

const props = withDefaults(defineProps<{ tone: StatusTone; size?: number }>(), { size: 14 })

const color = computed(() => ({
  frozen: 'var(--status-frozen)',
  active: 'var(--status-active)',
  wip: 'var(--status-wip)',
  blocked: 'var(--status-blocked)',
  done: 'var(--status-done)',
  none: 'var(--subtle)',
})[props.tone])
</script>

<template>
  <svg :width="size" :height="size" viewBox="0 0 14 14" fill="none" aria-hidden="true" class="shrink-0" :style="{ color }">
    <!-- frozen: dashed ring -->
    <circle v-if="tone === 'frozen'" cx="7" cy="7" r="5.75" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.2 2" />
    <!-- active: ring -->
    <circle v-else-if="tone === 'active'" cx="7" cy="7" r="5.75" stroke="currentColor" stroke-width="1.5" />
    <!-- wip: ring with a half pie -->
    <template v-else-if="tone === 'wip'">
      <circle cx="7" cy="7" r="5.75" stroke="currentColor" stroke-width="1.5" />
      <path d="M7 3.25 A3.75 3.75 0 0 1 7 10.75 Z" fill="currentColor" />
    </template>
    <!-- blocked: filled disc with a bar -->
    <template v-else-if="tone === 'blocked'">
      <circle cx="7" cy="7" r="6.5" fill="currentColor" />
      <rect x="3.75" y="6.15" width="6.5" height="1.7" rx="0.85" fill="var(--background)" />
    </template>
    <!-- done: filled disc with a check -->
    <template v-else-if="tone === 'done'">
      <circle cx="7" cy="7" r="6.5" fill="currentColor" />
      <path d="M4.4 7.2 6.2 9 9.7 5.3" stroke="var(--background)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
    </template>
    <!-- no category: dotted ring -->
    <circle v-else cx="7" cy="7" r="5.75" stroke="currentColor" stroke-width="1.5" stroke-dasharray="0.5 2.4" stroke-linecap="round" />
  </svg>
</template>
