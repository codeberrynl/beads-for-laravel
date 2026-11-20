<script setup lang="ts">
import { computed } from 'vue'
import { labelColor } from '@/lib/colors'

const props = defineProps<{ label: string }>()
const color = computed(() => labelColor(props.label))
</script>

<template>
  <span class="inline-flex h-5 max-w-40 shrink-0 items-center gap-1.5 rounded-full border px-2 text-[12.5px] text-muted-foreground">
    <span class="size-2 shrink-0 rounded-full" :style="{ background: color }" />
    <span class="truncate">{{ label }}</span>
  </span>
</template>
