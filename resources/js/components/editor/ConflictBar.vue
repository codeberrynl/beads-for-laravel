<script setup lang="ts">
import { ref } from 'vue'
import { TriangleAlert } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { copyText } from '@/lib/clipboard'
import type { Conflict } from '@/composables/useAutosave'
import { t } from '@/lib/i18n'
import { Button } from '@/components/ui/button'

/** Shown when a text field changed in bd while it was being edited (409). */
const props = defineProps<{ conflict: Conflict }>()
const emit = defineEmits<{ overwrite: []; takeTheirs: [] }>()

const showTheirs = ref(false)

async function copyMine() {
  await copyText(props.conflict.mine)
  toast.success(t('conflict.copied'))
}
</script>

<template>
  <div role="alert" class="rounded-lg border border-status-wip/40 bg-status-wip/10 px-3 py-2.5 text-[12.5px]">
    <div class="flex items-start gap-2">
      <TriangleAlert class="mt-0.5 size-3.5 shrink-0 text-status-wip" />
      <div class="min-w-0 flex-1">
        <p class="font-medium">{{ t('conflict.title') }}</p>
        <p class="text-muted-foreground">{{ t('conflict.body') }}</p>
        <button type="button" class="mt-1 text-[12px] text-muted-foreground underline decoration-dotted underline-offset-2 hover:text-foreground" @click="showTheirs = !showTheirs">
          {{ t('conflict.takeTheirs') }}…
        </button>
        <pre v-if="showTheirs" class="beads-source mt-1.5 max-h-40 overflow-auto rounded border bg-background/60 p-2 whitespace-pre-wrap">{{ conflict.theirs }}</pre>
      </div>
    </div>
    <div class="mt-2 flex flex-wrap gap-1.5 pl-5.5">
      <Button size="xs" variant="default" @click="emit('overwrite')">{{ t('conflict.overwrite') }}</Button>
      <Button size="xs" variant="outline" @click="emit('takeTheirs')">{{ t('conflict.takeTheirs') }}</Button>
      <Button size="xs" variant="ghost" @click="copyMine">{{ t('conflict.copyMine') }}</Button>
    </div>
  </div>
</template>
