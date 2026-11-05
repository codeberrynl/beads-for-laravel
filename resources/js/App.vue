<script setup lang="ts">
import { RouterView } from 'vue-router'
import AppSidebar from '@/components/AppSidebar.vue'
import { TooltipProvider } from '@/components/ui/tooltip'
import { Toaster } from '@/components/ui/sonner'
</script>

<template>
  <TooltipProvider :delay-duration="400">
    <div class="flex h-full">
      <AppSidebar workspace="beads" actor="" />
      <main class="min-w-0 flex-1 bg-background">
        <RouterView />
      </main>
    </div>
    <Toaster position="bottom-right" />
  </TooltipProvider>
</template>
