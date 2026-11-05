<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
</script>

<template>
  <div class="flex min-h-screen flex-col gap-4 p-8">
    <h1 class="text-lg font-semibold">Beads</h1>
    <div class="flex gap-2">
      <Button>Primary</Button>
      <Button variant="outline">Outline</Button>
      <Button variant="ghost">Ghost</Button>
      <Badge>Badge</Badge>
    </div>
    <div class="flex gap-3 text-sm">
      <span class="text-status-frozen">frozen</span>
      <span class="text-status-active">active</span>
      <span class="text-status-wip">wip</span>
      <span class="text-status-blocked">blocked</span>
      <span class="text-status-done">done</span>
    </div>
  </div>
</template>
