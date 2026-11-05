<script setup lang="ts">
import { computed } from 'vue'
import { RouterView } from 'vue-router'
import AppSidebar from '@/components/AppSidebar.vue'
import HealthScreen from '@/components/HealthScreen.vue'
import ErrorScreen from '@/components/ErrorScreen.vue'
import { TooltipProvider } from '@/components/ui/tooltip'
import { Toaster } from '@/components/ui/sonner'
import { healthOverride, useSnapshot } from '@/composables/useBeads'
import { useWorkspace } from '@/composables/useWorkspace'
import { viewCounts } from '@/lib/issues'
import { t } from '@/lib/i18n'
import CommandPalette from '@/components/CommandPalette.vue'
import { usePalette } from '@/composables/usePalette'
import { useCreateDialog } from '@/composables/useCreateDialog'
import { useShortcut } from '@/composables/useShortcut'

const { bootstrap, statuses, actor, workspaceName } = useWorkspace()
const snapshot = useSnapshot()

const health = computed(() => {
  if (healthOverride.value) {
    return healthOverride.value
  }

  const state = bootstrap.data.value?.health

  return state && state.state !== 'ok' ? state : null
})

const ready = computed(() => bootstrap.data.value !== undefined && health.value === null)
const counts = computed(() => viewCounts(snapshot.rows.value, { actor: actor.value, statuses: statuses.value }))

const palette = usePalette()
const createDialog = useCreateDialog()

useShortcut('mod+k', () => {
  palette.open.value = !palette.open.value
}, { inOverlay: true })

useShortcut('c', () => {
  if (!ready.value) return false
  createDialog.open()
})

async function recheck() {
  healthOverride.value = null
  await bootstrap.refetch()
  await snapshot.refetch()
}
</script>

<template>
  <TooltipProvider :delay-duration="400">
    <div v-if="bootstrap.isPending.value" class="grid h-full place-items-center text-[13px] text-muted-foreground">
      {{ t('app.loading') }}
    </div>
    <ErrorScreen
      v-else-if="bootstrap.isError.value && !bootstrap.data.value"
      :message="bootstrap.error.value?.message ?? ''"
      :checking="bootstrap.isFetching.value"
      @retry="recheck"
    />
    <HealthScreen v-else-if="health" :health="health" :checking="bootstrap.isFetching.value" @retry="recheck" />
    <div v-else-if="ready" class="flex h-full">
      <AppSidebar :workspace="workspaceName" :actor="actor" :counts="counts" @search="palette.show" @create="createDialog.open()" />
      <main class="min-w-0 flex-1 bg-background">
        <RouterView />
      </main>
      <CommandPalette />
    </div>
    <Toaster position="bottom-right" :theme="'system'" />
  </TooltipProvider>
</template>
