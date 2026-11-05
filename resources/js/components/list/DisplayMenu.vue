<script setup lang="ts">
import { SlidersHorizontal } from '@lucide/vue'
import { GROUPS, SORTS, type DisplayOptions, type GroupBy, type SortBy } from '@/lib/filters'
import { t } from '@/lib/i18n'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

defineProps<{ display: DisplayOptions; groupable?: boolean }>()
const emit = defineEmits<{ change: [patch: Partial<DisplayOptions>] }>()
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" size="sm" class="h-7 gap-1.5 px-2 text-[13px] text-muted-foreground">
        <SlidersHorizontal class="size-3.5" />
        {{ t('filters.display') }}
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-48">
      <template v-if="groupable !== false">
        <DropdownMenuLabel class="text-xs text-muted-foreground">{{ t('display.grouping') }}</DropdownMenuLabel>
        <DropdownMenuRadioGroup :model-value="display.group" @update:model-value="(value) => emit('change', { group: value as GroupBy })">
          <DropdownMenuRadioItem v-for="group in GROUPS" :key="group" :value="group">{{ t(`display.group.${group}`) }}</DropdownMenuRadioItem>
        </DropdownMenuRadioGroup>
        <DropdownMenuSeparator />
      </template>
      <DropdownMenuLabel class="text-xs text-muted-foreground">{{ t('display.ordering') }}</DropdownMenuLabel>
      <DropdownMenuRadioGroup :model-value="display.sort" @update:model-value="(value) => emit('change', { sort: value as SortBy })">
        <DropdownMenuRadioItem v-for="sort in SORTS" :key="sort" :value="sort">{{ t(`display.sort.${sort}`) }}</DropdownMenuRadioItem>
      </DropdownMenuRadioGroup>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
