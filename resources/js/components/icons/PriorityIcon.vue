<script setup lang="ts">
withDefaults(defineProps<{ priority: number; size?: number }>(), { size: 14 })
</script>

<template>
  <svg :width="size" :height="size" viewBox="0 0 14 14" aria-hidden="true" class="shrink-0">
    <!-- P0 Urgent: an exclamation mark in an orange square -->
    <template v-if="priority <= 0">
      <rect x="0.5" y="0.5" width="13" height="13" rx="3" fill="var(--priority-urgent)" />
      <rect x="6.1" y="3" width="1.8" height="5.2" rx="0.9" fill="white" />
      <circle cx="7" cy="10.3" r="1" fill="white" />
    </template>
    <!-- P4 Backlog: three dashes -->
    <template v-else-if="priority >= 4">
      <rect x="1" y="6.25" width="3" height="1.5" rx="0.75" fill="var(--subtle)" />
      <rect x="5.5" y="6.25" width="3" height="1.5" rx="0.75" fill="var(--subtle)" />
      <rect x="10" y="6.25" width="3" height="1.5" rx="0.75" fill="var(--subtle)" />
    </template>
    <!-- P1–P3: signal bars, 3/2/1 filled -->
    <template v-else>
      <rect x="1.5" y="8" width="2.5" height="4.5" rx="0.75" :fill="priority <= 3 ? 'var(--priority-high)' : 'var(--subtle)'" />
      <rect x="5.75" y="5" width="2.5" height="7.5" rx="0.75" :fill="priority <= 2 ? 'var(--priority-high)' : 'var(--subtle)'" :fill-opacity="priority <= 2 ? 1 : 0.4" />
      <rect x="10" y="1.5" width="2.5" height="11" rx="0.75" :fill="priority <= 1 ? 'var(--priority-high)' : 'var(--subtle)'" :fill-opacity="priority <= 1 ? 1 : 0.4" />
    </template>
  </svg>
</template>
