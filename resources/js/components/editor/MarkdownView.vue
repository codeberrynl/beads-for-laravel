<script setup lang="ts">
import { computed, watch } from 'vue'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { isLossless, markdownExtensions } from '@/lib/markdown'

/** Read-only markdown, rendered through the editor schema (never raw HTML). */
const props = defineProps<{ markdown: string }>()

const lossless = computed(() => isLossless(props.markdown))

const editor = useEditor({
  editable: false,
  extensions: markdownExtensions(),
  content: props.markdown,
  contentType: 'markdown',
})

watch(
  () => props.markdown,
  (markdown) => editor.value?.commands.setContent(markdown, { contentType: 'markdown', emitUpdate: false }),
)
</script>

<template>
  <pre v-if="!lossless" class="beads-source whitespace-pre-wrap">{{ markdown }}</pre>
  <EditorContent v-else :editor="editor" class="beads-prose" />
</template>
