<script setup lang="ts">
import { labelColor } from '@/lib/colors'

defineProps<{ label: string }>()
</script>

<template>
  <span class="size-2 shrink-0 rounded-full" :style="{ background: labelColor(label) }" />
</template>
