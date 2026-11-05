<script setup lang="ts">
import { computed } from 'vue'
import { X } from '@lucide/vue'
import type { IssueRow } from '@/lib/types'
import { NO_PARENT, UNASSIGNED, type ListFilters } from '@/lib/filters'
import { priorityLabel } from '@/lib/priority'
import { statusLabel } from '@/lib/status'
import { typeLabel } from '@/lib/types-meta'
import { t } from '@/lib/i18n'

type Key = 'status' | 'priority' | 'type' | 'label' | 'assignee' | 'parent'

const props = defineProps<{ filters: ListFilters; byId: Map<string, IssueRow> }>()
const emit = defineEmits<{ remove: [key: Key]; clear: [] }>()

const format: Record<Key, (value: string | number) => string> = {
  status: (value) => statusLabel(String(value)),
  priority: (value) => priorityLabel(Number(value)),
  type: (value) => typeLabel(String(value)),
  label: (value) => String(value),
  assignee: (value) => (value === UNASSIGNED ? t('filters.unassigned') : String(value)),
  parent: (value) => (value === NO_PARENT ? t('filters.noParent') : props.byId.get(String(value))?.title ?? String(value)),
}

const chips = computed(() =>
  (['status', 'priority', 'type', 'label', 'assignee', 'parent'] as Key[])
    .filter((key) => props.filters[key].length > 0)
    .map((key) => ({
      key,
      field: t(`filters.${key}`),
      operator: props.filters[key].length > 1 ? t('filters.isAnyOf') : t('filters.is'),
      values: (props.filters[key] as Array<string | number>).map((value) => format[key](value)).join(', '),
    })),
)
</script>

<template>
  <div v-if="chips.length" class="flex flex-wrap items-center gap-1.5 border-b px-4 py-2">
    <span v-for="chip in chips" :key="chip.key" class="inline-flex h-6 items-center overflow-hidden rounded-md border text-[12px]">
      <span class="px-2 text-foreground">{{ chip.field }}</span>
      <span class="border-l px-2 text-muted-foreground">{{ chip.operator }}</span>
      <span class="max-w-72 truncate border-l px-2 text-foreground">{{ chip.values }}</span>
      <button type="button" class="grid h-full w-6 place-items-center border-l text-muted-foreground hover:bg-accent hover:text-foreground" :aria-label="t('filters.clear')" @click="emit('remove', chip.key)">
        <X class="size-3" />
      </button>
    </span>
    <button type="button" class="ml-1 text-[12px] text-muted-foreground hover:text-foreground" @click="emit('clear')">{{ t('filters.clear') }}</button>
  </div>
</template>
