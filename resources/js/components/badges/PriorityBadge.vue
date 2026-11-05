<script setup lang="ts">
import PriorityIcon from '@/components/icons/PriorityIcon.vue'
import { priorityTooltip } from '@/lib/priority'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'

defineProps<{ priority: number }>()
</script>

<template>
  <Tooltip>
    <TooltipTrigger as-child>
      <span class="inline-flex shrink-0" :aria-label="priorityTooltip(priority)"><PriorityIcon :priority="priority" /></span>
    </TooltipTrigger>
    <TooltipContent>{{ priorityTooltip(priority) }}</TooltipContent>
  </Tooltip>
</template>
