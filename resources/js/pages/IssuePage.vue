<script setup lang="ts">
import ViewHeader from '@/components/ViewHeader.vue'

defineProps<{ id: string }>()
</script>

<template>
  <div class="flex h-full flex-col">
    <ViewHeader :title="id" />
  </div>
</template>
