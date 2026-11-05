<script setup lang="ts">
import { computed } from 'vue'
import { OctagonMinus } from '@lucide/vue'
import { t } from '@/lib/i18n'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'

const props = defineProps<{ blockedBy: string[] }>()
const label = computed(() => t('badges.blockedBy', { ids: props.blockedBy.join(', ') }))
</script>

<template>
  <Tooltip>
    <TooltipTrigger as-child>
      <span class="inline-flex shrink-0 items-center text-status-blocked" :aria-label="label">
        <OctagonMinus class="size-3.5" :stroke-width="2" />
      </span>
    </TooltipTrigger>
    <TooltipContent>{{ label }}</TooltipContent>
  </Tooltip>
</template>
