<script setup lang="ts">
import { computed, ref } from 'vue'
import { ChevronRight, Plus } from '@lucide/vue'
import MarkdownField from '@/components/editor/MarkdownField.vue'
import { t } from '@/lib/i18n'
import type { Issue } from '@/lib/types'

/** Description first; design, acceptance criteria and notes as sections. */
const props = defineProps<{ issue: Issue }>()

type Section = 'design' | 'acceptance_criteria' | 'notes'

const sections: { field: Section; label: string; placeholder: string }[] = [
  { field: 'design', label: 'detail.design', placeholder: 'detail.designPlaceholder' },
  { field: 'acceptance_criteria', label: 'detail.acceptance', placeholder: 'detail.acceptancePlaceholder' },
  { field: 'notes', label: 'detail.notes', placeholder: 'detail.notesPlaceholder' },
]

const opened = ref(new Set<Section>())
const collapsed = ref(new Set<Section>())

const visible = computed(() => sections.filter((section) => props.issue[section.field] !== '' || opened.value.has(section.field)))
const hidden = computed(() => sections.filter((section) => !visible.value.includes(section)))

function toggle(field: Section) {
  const next = new Set(collapsed.value)
  next.has(field) ? next.delete(field) : next.add(field)
  collapsed.value = next
}

function add(field: Section) {
  opened.value = new Set([...opened.value, field])
}
</script>

<template>
  <div>
    <MarkdownField :key="`${issue.id}-description`" :issue-id="issue.id" field="description" :value="issue.description" :placeholder="t('detail.descriptionPlaceholder')" class="mt-3" />

    <section v-for="section in visible" :key="section.field" class="mt-6">
      <button type="button" class="mb-1.5 flex items-center gap-1 text-[12px] font-medium text-muted-foreground hover:text-foreground" @click="toggle(section.field)">
        <ChevronRight class="size-3.5 transition-transform" :class="{ 'rotate-90': !collapsed.has(section.field) }" />
        {{ t(section.label) }}
      </button>
      <MarkdownField
        v-show="!collapsed.has(section.field)"
        :key="`${issue.id}-${section.field}`"
        :issue-id="issue.id"
        :field="section.field"
        :value="issue[section.field]"
        :placeholder="t(section.placeholder)"
        compact
      />
    </section>

    <div v-if="hidden.length" class="mt-4 flex flex-wrap gap-1.5">
      <button
        v-for="section in hidden"
        :key="section.field"
        type="button"
        class="inline-flex h-6 items-center gap-1 rounded-md border border-dashed px-2 text-[12px] text-muted-foreground hover:border-solid hover:text-foreground"
        @click="add(section.field)"
      >
        <Plus class="size-3" /> {{ t(section.label) }}
      </button>
    </div>
  </div>
</template>
