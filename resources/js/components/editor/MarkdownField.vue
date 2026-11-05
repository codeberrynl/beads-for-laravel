<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { Placeholder } from '@tiptap/extensions'
import { Code2, TriangleAlert, Type } from '@lucide/vue'
import SaveIndicator from '@/components/editor/SaveIndicator.vue'
import ConflictBar from '@/components/editor/ConflictBar.vue'
import { useAutosave } from '@/composables/useAutosave'
import { useIssueActions } from '@/composables/useIssueActions'
import { lossReasons, markdownExtensions, roundTrip } from '@/lib/markdown'
import { ApiError } from '@/lib/api'
import { t } from '@/lib/i18n'
import type { TextField } from '@/lib/types'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'

/**
 * A markdown text field of an issue with three round-trip safeguards:
 * 1. it only saves when the editor output differs from the baseline (the
 *    original serialised once by the editor), so opening never writes;
 * 2. a toggle to edit the markdown source;
 * 3. content the editor cannot keep (raw HTML, footnotes, tables, …) opens
 *    in source mode automatically.
 * Autosave after 2 s without typing, on blur and with Cmd+Enter; the raw
 * original is sent as the base for the conflict check.
 */
const props = defineProps<{
  issueId: string
  field: Exclude<TextField, 'title'>
  value: string
  placeholder: string
  compact?: boolean
}>()

const actions = useIssueActions()

/** What the server had when we started editing (sent as the base). */
const base = ref(props.value)
/** The editor's serialisation of `base`: equal output means "no change". */
const baseline = ref('')
const reasons = ref(lossReasons(props.value))
const forcedSource = computed(() => reasons.value.length > 0)
const sourceMode = ref(forcedSource.value)
const source = ref(props.value)
const focused = ref(false)

const autosave = useAutosave(save)

const editor = useEditor({
  extensions: markdownExtensions([Placeholder.configure({ placeholder: () => props.placeholder })]),
  content: forcedSource.value ? '' : props.value,
  contentType: 'markdown',
  editorProps: {
    attributes: { class: 'beads-prose outline-none', 'aria-label': props.placeholder },
    handleKeyDown: (_view, event) => {
      if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        void autosave.flush()
        return true
      }
      return false
    },
  },
  onCreate: ({ editor: instance }) => {
    baseline.value = forcedSource.value ? props.value : instance.getMarkdown()
  },
  onUpdate: () => autosave.touch(),
  onFocus: () => (focused.value = true),
  onBlur: () => {
    focused.value = false
    void autosave.flush()
  },
})

function current(): string {
  return sourceMode.value ? source.value : (editor.value?.getMarkdown() ?? baseline.value)
}

const unchanged = (value: string) => (sourceMode.value ? value === base.value : value === baseline.value)

async function save(): Promise<void> {
  const value = current()

  if (autosave.state.value === 'conflict' || unchanged(value)) {
    if (autosave.state.value === 'dirty') autosave.state.value = 'idle'
    return
  }

  autosave.state.value = 'saving'

  try {
    await actions.update(props.issueId, { [props.field]: value, base: { [props.field]: base.value } }, { optimistic: false, quiet: true })
    base.value = value
    baseline.value = sourceMode.value ? value : current()
    autosave.state.value = 'saved'
  } catch (error) {
    if (error instanceof ApiError && error.isConflict) {
      autosave.state.value = 'conflict'
      autosave.conflict.value = { theirs: error.body.theirs ?? '', mine: value }
    }
    throw error
  }
}

/** Load a new server value (or a resolved conflict) into the editor. */
function load(value: string) {
  base.value = value
  source.value = value
  reasons.value = lossReasons(value)

  if (reasons.value.length > 0) {
    sourceMode.value = true
    baseline.value = value
  } else if (!sourceMode.value) {
    editor.value?.commands.setContent(value, { contentType: 'markdown', emitUpdate: false })
    baseline.value = editor.value?.getMarkdown() ?? roundTrip(value)
  } else {
    baseline.value = value
  }
}

// Outside changes flow in while we are not editing.
watch(
  () => props.value,
  (value) => {
    if (value !== base.value && !focused.value && ['idle', 'saved'].includes(autosave.state.value)) {
      load(value)
    }
  },
)

async function toggleSource() {
  if (sourceMode.value) {
    if (forcedSource.value || lossReasons(source.value).length > 0) return
    const text = source.value
    sourceMode.value = false
    await nextTick()
    editor.value?.commands.setContent(text, { contentType: 'markdown', emitUpdate: false })
    // Unchanged text keeps the baseline, so switching back never writes.
    if (text === base.value) baseline.value = editor.value?.getMarkdown() ?? baseline.value
    else autosave.touch()
  } else {
    const markdown = current()
    source.value = markdown === baseline.value ? base.value : markdown
    sourceMode.value = true
  }
}

function onSourceInput() {
  autosave.touch()
}

// Conflict bar choices.
async function overwrite() {
  const mine = autosave.conflict.value?.mine ?? current()
  base.value = autosave.conflict.value?.theirs ?? base.value
  autosave.conflict.value = null
  autosave.state.value = 'dirty'
  if (sourceMode.value) source.value = mine
  baseline.value = '\u0000'
  await autosave.flush()
}

function takeTheirs() {
  const theirs = autosave.conflict.value?.theirs ?? ''
  autosave.conflict.value = null
  autosave.state.value = 'idle'
  load(theirs)
}

onBeforeUnmount(() => editor.value?.destroy())

const showPlaceholder = computed(() => sourceMode.value && source.value === '')
</script>

<template>
  <div class="group/field relative">
    <ConflictBar
      v-if="autosave.state.value === 'conflict' && autosave.conflict.value"
      :conflict="autosave.conflict.value"
      class="mb-2"
      @overwrite="overwrite"
      @take-theirs="takeTheirs"
    />

    <p v-if="forcedSource" class="mb-2 flex items-start gap-1.5 text-[12px] text-muted-foreground">
      <TriangleAlert class="mt-0.5 size-3.5 shrink-0 text-status-wip" />
      {{ t('editor.sourceForced') }}
    </p>

    <textarea
      v-if="sourceMode"
      v-model="source"
      :placeholder="placeholder"
      class="beads-source block w-full resize-none overflow-hidden bg-transparent outline-none placeholder:text-subtle"
      :class="{ 'min-h-16': !compact }"
      :rows="Math.max(compact ? 2 : 4, source.split('\n').length + 1)"
      :aria-label="placeholder"
      @input="onSourceInput"
      @focus="focused = true"
      @blur="focused = false; void autosave.flush()"
      @keydown.meta.enter.prevent="autosave.flush()"
      @keydown.ctrl.enter.prevent="autosave.flush()"
    />
    <EditorContent v-else :editor="editor" :class="compact ? 'min-h-6' : 'min-h-16'" />
    <span v-if="showPlaceholder" class="sr-only">{{ placeholder }}</span>

    <div class="mt-1 flex h-5 items-center gap-2 opacity-0 transition-opacity group-focus-within/field:opacity-100 group-hover/field:opacity-100" :class="{ 'opacity-100': autosave.state.value !== 'idle' }">
      <SaveIndicator :state="autosave.state.value" />
      <Tooltip>
        <TooltipTrigger as-child>
          <button
            type="button"
            class="ml-auto inline-flex h-5 items-center gap-1 rounded px-1 text-[11.5px] text-subtle hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
            :disabled="sourceMode && forcedSource"
            :aria-pressed="sourceMode"
            @mousedown.prevent
            @click="toggleSource"
          >
            <component :is="sourceMode ? Type : Code2" class="size-3" />
            {{ sourceMode ? t('editor.richMode') : t('editor.sourceMode') }}
          </button>
        </TooltipTrigger>
        <TooltipContent>{{ t('editor.toggleSource') }}</TooltipContent>
      </Tooltip>
    </div>
  </div>
</template>
