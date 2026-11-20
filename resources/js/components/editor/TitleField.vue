<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue'
import ConflictBar from '@/components/editor/ConflictBar.vue'
import SaveIndicator from '@/components/editor/SaveIndicator.vue'
import { useAutosave } from '@/composables/useAutosave'
import { useIssueActions } from '@/composables/useIssueActions'
import { ApiError } from '@/lib/api'
import { t } from '@/lib/i18n'

/** The title as plain text; Enter saves, like the other fields it autosaves. */
const props = defineProps<{ issueId: string; value: string }>()

const actions = useIssueActions()
const base = ref(props.value)
const text = ref(props.value)
const focused = ref(false)
const input = ref<HTMLTextAreaElement | null>(null)

const autosave = useAutosave(save)

async function save() {
  const value = text.value.replace(/\s*\n\s*/g, ' ').trim()

  if (value === '' || value === base.value || autosave.state.value === 'conflict') {
    if (value === '') text.value = base.value
    if (autosave.state.value === 'dirty') autosave.state.value = 'idle'
    return
  }

  autosave.state.value = 'saving'

  try {
    await actions.update(props.issueId, { title: value, base: { title: base.value } }, { quiet: true })
    base.value = value
    autosave.state.value = 'saved'
  } catch (error) {
    if (error instanceof ApiError && error.isConflict) {
      autosave.state.value = 'conflict'
      autosave.conflict.value = { theirs: error.body.theirs ?? '', mine: value }
    }
    throw error
  }
}

function resize() {
  const element = input.value
  if (!element) return
  element.style.height = 'auto'
  element.style.height = `${element.scrollHeight}px`
}

watch(
  () => props.value,
  (value) => {
    if (!focused.value && value !== base.value && ['idle', 'saved'].includes(autosave.state.value)) {
      base.value = value
      text.value = value
      void nextTick(resize)
    }
  },
)

onMounted(resize)

async function overwrite() {
  base.value = autosave.conflict.value?.theirs ?? base.value
  autosave.conflict.value = null
  autosave.state.value = 'dirty'
  await autosave.flush()
}

function takeTheirs() {
  const theirs = autosave.conflict.value?.theirs ?? base.value
  autosave.conflict.value = null
  autosave.state.value = 'idle'
  base.value = theirs
  text.value = theirs
  void nextTick(resize)
}
</script>

<template>
  <div class="group/title">
    <ConflictBar v-if="autosave.state.value === 'conflict' && autosave.conflict.value" :conflict="autosave.conflict.value" class="mb-2" @overwrite="overwrite" @take-theirs="takeTheirs" />
    <textarea
      ref="input"
      v-model="text"
      rows="1"
      :placeholder="t('detail.titlePlaceholder')"
      :aria-label="t('detail.titlePlaceholder')"
      class="block w-full resize-none overflow-hidden bg-transparent text-[21px] font-semibold leading-snug outline-none placeholder:text-subtle"
      @input="resize(); autosave.touch()"
      @focus="focused = true"
      @blur="focused = false; void autosave.flush()"
      @keydown.enter.prevent="($event.target as HTMLTextAreaElement).blur()"
      @keydown.esc.stop="text = base; ($event.target as HTMLTextAreaElement).blur()"
    />
    <div class="h-4">
      <SaveIndicator v-if="autosave.state.value !== 'idle' && autosave.state.value !== 'dirty'" :state="autosave.state.value" />
    </div>
  </div>
</template>
