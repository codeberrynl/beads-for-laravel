<script setup lang="ts">
import { computed } from 'vue'
import { Bot, UserRound } from '@lucide/vue'
import { hueOf, initials } from '@/lib/colors'

const props = withDefaults(defineProps<{ name: string | null; agent?: boolean; size?: number }>(), { agent: false, size: 18 })

const style = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
  fontSize: `${Math.round(props.size * 0.5)}px`,
  background: props.name ? `oklch(0.55 0.12 ${hueOf(props.name)})` : 'transparent',
}))
</script>

<template>
  <span
    class="grid shrink-0 place-items-center rounded-full font-semibold text-white"
    :class="{ 'border border-dashed border-subtle text-subtle': !name }"
    :style="style"
    :title="name ?? undefined"
  >
    <UserRound v-if="!name" :size="Math.round(size * 0.6)" />
    <Bot v-else-if="agent" :size="Math.round(size * 0.62)" />
    <template v-else>{{ initials(name) }}</template>
  </span>
</template>
