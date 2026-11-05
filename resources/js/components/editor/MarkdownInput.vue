<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { Placeholder } from '@tiptap/extensions'
import { markdownExtensions } from '@/lib/markdown'

/** A Tiptap markdown input without autosave (for the create modal). */
const props = defineProps<{ modelValue: string; placeholder: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string]; submit: [] }>()

const editor = useEditor({
  extensions: markdownExtensions([Placeholder.configure({ placeholder: () => props.placeholder })]),
  content: props.modelValue,
  contentType: 'markdown',
  editorProps: {
    attributes: { class: 'beads-prose outline-none min-h-20', 'aria-label': props.placeholder },
    handleKeyDown: (_view, event) => {
      if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        emit('submit')
        return true
      }
      return false
    },
  },
  onUpdate: ({ editor: instance }) => emit('update:modelValue', instance.isEmpty ? '' : instance.getMarkdown()),
})

// Cleared from outside (Create more): reset the editor too.
watch(
  () => props.modelValue,
  (value) => {
    if (value === '' && editor.value && !editor.value.isEmpty) {
      editor.value.commands.clearContent()
    }
  },
)

onBeforeUnmount(() => editor.value?.destroy())
</script>

<template>
  <EditorContent :editor="editor" />
</template>
