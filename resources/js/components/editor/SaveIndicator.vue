<script setup lang="ts">
import { Check, CircleAlert, Loader2 } from '@lucide/vue'
import type { SaveState } from '@/composables/useAutosave'
import { t } from '@/lib/i18n'

defineProps<{ state: SaveState }>()
</script>

<template>
  <span class="inline-flex h-5 items-center gap-1 text-[11.5px] text-subtle" aria-live="polite">
    <template v-if="state === 'saving'"><Loader2 class="size-3 animate-spin" /> {{ t('editor.saving') }}</template>
    <template v-else-if="state === 'saved'"><Check class="size-3" /> {{ t('editor.saved') }}</template>
    <template v-else-if="state === 'dirty'">{{ t('editor.unsaved') }} · {{ t('editor.saveHint') }}</template>
    <template v-else-if="state === 'error'"><CircleAlert class="size-3 text-destructive" /> <span class="text-destructive">{{ t('editor.error') }}</span></template>
  </span>
</template>
