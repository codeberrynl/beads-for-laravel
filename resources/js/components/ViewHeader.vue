<script setup lang="ts">
defineProps<{ title: string; count?: number }>()
</script>

<template>
  <header class="flex h-11 shrink-0 items-center gap-2 border-b px-4">
    <h1 class="flex min-w-0 items-center gap-2 text-[13px] font-medium">
      <slot name="icon" />
      <span class="truncate">{{ title }}</span>
      <span v-if="count !== undefined" class="text-xs font-normal tabular-nums text-muted-foreground">{{ count }}</span>
    </h1>
    <div class="ml-auto flex items-center gap-1.5">
      <slot name="actions" />
    </div>
  </header>
</template>
