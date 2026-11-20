<script setup lang="ts">
import { computed, ref } from 'vue'
import { Check, Copy, RefreshCw, TriangleAlert } from '@lucide/vue'
import { t } from '@/lib/i18n'
import type { Health } from '@/lib/types'
import { Button } from '@/components/ui/button'
import { copyText } from '@/lib/clipboard'

const props = defineProps<{ health: Health; checking?: boolean }>()
const emit = defineEmits<{ retry: [] }>()

const copied = ref<number | null>(null)

const commands = computed<string[]>(() => {
  const path = props.health.workspace

  switch (props.health.state) {
    case 'binary_missing':
      return ['brew install beads', '# or point the package at your bd:', 'BEADS_BINARY=/absolute/path/to/bd']
    case 'version_too_old':
      return ['brew upgrade beads', 'bd --version']
    case 'workspace_missing':
      return [`cd ${path}`, 'bd init', '# or point the package at an existing workspace:', 'BEADS_PATH=/path/to/project']
    case 'schema_migration_required':
      return [`cd ${path}`, 'bd migrate schema']
    default:
      return []
  }
})

const body = computed(() =>
  t(`health.body.${props.health.state}`, {
    minimum: props.health.minimum_version,
    version: props.health.version ?? t('app.unknown'),
    path: props.health.workspace,
  }),
)

async function copy(index: number, text: string) {
  await copyText(text)
  copied.value = index
  setTimeout(() => (copied.value = null), 1500)
}
</script>

<template>
  <div class="grid h-full place-items-center bg-background p-6">
    <div class="w-full max-w-lg">
      <div class="mb-5 grid size-10 place-items-center rounded-lg border bg-card text-status-wip">
        <TriangleAlert class="size-5" />
      </div>
      <h1 class="text-lg font-semibold">{{ t(`health.title.${health.state}`) }}</h1>
      <p class="mt-2 text-[14.5px] leading-relaxed text-muted-foreground">{{ body }}</p>

      <div v-if="health.state === 'binary_missing' && health.searched.length" class="mt-4 text-xs text-muted-foreground">
        <div class="mb-1 font-medium text-foreground">{{ t('health.searched') }}</div>
        <ul class="space-y-0.5 font-mono">
          <li v-for="path in health.searched" :key="path">{{ path }}</li>
        </ul>
      </div>

      <div v-if="commands.length" class="mt-5">
        <div class="mb-1.5 text-xs font-medium">{{ t('health.commands') }}</div>
        <div class="overflow-hidden rounded-lg border bg-card">
          <div v-for="(command, index) in commands" :key="index" class="group flex items-center gap-2 border-b px-3 py-2 font-mono text-[13.5px] last:border-b-0">
            <span :class="command.startsWith('#') ? 'text-subtle' : 'text-foreground'" class="flex-1 break-all">
              <span v-if="!command.startsWith('#')" class="mr-2 select-none text-subtle">$</span>{{ command }}
            </span>
            <button
              v-if="!command.startsWith('#')"
              type="button"
              class="text-muted-foreground opacity-0 transition group-hover:opacity-100 hover:text-foreground"
              :aria-label="t('app.copy')"
              @click="copy(index, command)"
            >
              <Check v-if="copied === index" class="size-3.5" />
              <Copy v-else class="size-3.5" />
            </button>
          </div>
        </div>
      </div>

      <Button class="mt-6" size="sm" variant="outline" :disabled="checking" @click="emit('retry')">
        <RefreshCw class="size-3.5" :class="{ 'animate-spin': checking }" />
        {{ t('health.checkAgain') }}
      </Button>
    </div>
  </div>
</template>
