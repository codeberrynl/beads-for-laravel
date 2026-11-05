<script setup lang="ts" generic="T extends string | number | null">
import { computed, nextTick, ref, watch, type Component } from 'vue'
import { Check, Plus } from '@lucide/vue'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { t } from '@/lib/i18n'

export interface PickerOption<V> {
  value: V
  label: string
  hint?: string
  keywords?: string
  icon?: Component
  iconProps?: Record<string, unknown>
}

/**
 * A searchable popover list (Linear-style picker). Single select closes on
 * choice; multiple keeps it open and toggles. `creatable` offers the typed
 * text as a new value.
 */
const props = withDefaults(
  defineProps<{
    options: PickerOption<T>[]
    selected: T[]
    multiple?: boolean
    creatable?: boolean
    createLabel?: (text: string) => string
    placeholder?: string
    align?: 'start' | 'center' | 'end'
    width?: string
  }>(),
  { multiple: false, creatable: false, placeholder: undefined, align: 'start', width: '15rem', createLabel: undefined },
)

const emit = defineEmits<{ select: [value: T]; create: [text: string] }>()

const open = ref(false)
const query = ref('')
const index = ref(0)
const input = ref<HTMLInputElement | null>(null)
const list = ref<HTMLElement | null>(null)

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()

  return q === '' ? props.options : props.options.filter((option) => `${option.label} ${option.hint ?? ''} ${option.keywords ?? ''}`.toLowerCase().includes(q))
})

const canCreate = computed(() => {
  const text = query.value.trim()

  return props.creatable && text !== '' && !props.options.some((option) => option.label.toLowerCase() === text.toLowerCase())
})

const count = computed(() => filtered.value.length + (canCreate.value ? 1 : 0))

watch(query, () => (index.value = 0))

watch(open, async (value) => {
  if (value) {
    query.value = ''
    index.value = Math.max(0, filtered.value.findIndex((option) => props.selected.includes(option.value)))
    await nextTick()
    input.value?.focus()
  }
})

function choose(position: number) {
  if (position < filtered.value.length) {
    const option = filtered.value[position]
    if (!option) return
    emit('select', option.value)
    if (!props.multiple) open.value = false
  } else if (canCreate.value) {
    emit('create', query.value.trim())
    query.value = ''
    if (!props.multiple) open.value = false
  }
}

function move(delta: number) {
  if (!count.value) return
  index.value = (index.value + delta + count.value) % count.value
  void nextTick(() => list.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' }))
}
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <slot :open="open" />
    </PopoverTrigger>
    <PopoverContent :align="align" class="p-0" :style="{ width }" @keydown.esc.stop>
      <input
        ref="input"
        v-model="query"
        :placeholder="placeholder ?? t('app.search')"
        class="h-9 w-full border-b bg-transparent px-3 text-[13px] outline-none placeholder:text-subtle"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
        @keydown.enter.prevent="choose(index)"
      />
      <div ref="list" role="listbox" :aria-multiselectable="multiple" class="max-h-72 overflow-y-auto p-1">
        <div
          v-for="(option, position) in filtered"
          :key="String(option.value)"
          role="option"
          :aria-selected="selected.includes(option.value)"
          :data-active="position === index"
          class="flex h-8 cursor-default items-center gap-2 rounded-sm px-2 text-[13px] data-[active=true]:bg-accent"
          @mousemove="index = position"
          @click="choose(position)"
        >
          <component :is="option.icon" v-if="option.icon" v-bind="option.iconProps" />
          <span class="truncate">{{ option.label }}</span>
          <span v-if="option.hint" class="ml-auto shrink-0 pl-2 text-[11.5px] text-subtle">{{ option.hint }}</span>
          <Check v-if="selected.includes(option.value)" class="size-3.5 shrink-0 text-foreground" :class="{ 'ml-auto': !option.hint, 'ml-1': option.hint }" />
        </div>
        <div
          v-if="canCreate"
          role="option"
          :data-active="index === filtered.length"
          class="flex h-8 cursor-default items-center gap-2 rounded-sm px-2 text-[13px] data-[active=true]:bg-accent"
          @mousemove="index = filtered.length"
          @click="choose(filtered.length)"
        >
          <Plus class="size-3.5 text-muted-foreground" />
          <span class="truncate">{{ createLabel ? createLabel(query.trim()) : query.trim() }}</span>
        </div>
        <div v-if="!count" class="px-2 py-3 text-center text-[12px] text-muted-foreground">{{ t('app.noResults') }}</div>
      </div>
    </PopoverContent>
  </Popover>
</template>
