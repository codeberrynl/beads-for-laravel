<script setup lang="ts">
import { ref } from 'vue'
import { Search, X } from '@lucide/vue'
import { t } from '@/lib/i18n'

defineProps<{ modelValue: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const input = ref<HTMLInputElement | null>(null)

defineExpose({ focus: () => input.value?.focus() })
</script>

<template>
  <label class="flex h-7 w-56 items-center gap-1.5 rounded-md border bg-background px-2 text-[13px] focus-within:border-ring focus-within:ring-2 focus-within:ring-ring/20">
    <Search class="size-3.5 shrink-0 text-muted-foreground" />
    <input
      ref="input"
      data-search-input
      :value="modelValue"
      type="search"
      :placeholder="t('filters.searchPlaceholder')"
      class="min-w-0 flex-1 bg-transparent outline-none placeholder:text-subtle [&::-webkit-search-cancel-button]:hidden"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      @keydown.esc.stop="($event.target as HTMLInputElement).blur()"
    />
    <button v-if="modelValue" type="button" class="text-muted-foreground hover:text-foreground" :aria-label="t('filters.clear')" @click="emit('update:modelValue', '')">
      <X class="size-3" />
    </button>
  </label>
</template>
