<script setup lang="ts">
import { Bot } from '@lucide/vue'
import { t } from '@/lib/i18n'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'

withDefaults(defineProps<{ name: string; compact?: boolean }>(), { compact: false })
</script>

<template>
  <Tooltip>
    <TooltipTrigger as-child>
      <span
        class="inline-flex h-5 shrink-0 items-center gap-1 rounded-full px-1.5 text-[11px] font-medium"
        :style="{ color: 'var(--agent)', background: 'color-mix(in oklch, var(--agent) 14%, transparent)' }"
      >
        <Bot class="size-3" />
        <span v-if="!compact">{{ t('badges.agent') }}</span>
      </span>
    </TooltipTrigger>
    <TooltipContent>{{ t('badges.agentTooltip', { name }) }}</TooltipContent>
  </Tooltip>
</template>
