<script setup lang="ts">
import { X } from '@lucide/vue'
import StatusIcon from '@/components/icons/StatusIcon.vue'
import PriorityIcon from '@/components/icons/PriorityIcon.vue'
import { toneOf } from '@/lib/status'
import { t } from '@/lib/i18n'
import type { IssueRow, StatusDefinition } from '@/lib/types'

withDefaults(defineProps<{ id: string; row?: IssueRow; statuses: StatusDefinition[]; note?: string; removable?: boolean }>(), { removable: true, row: undefined, note: undefined })
const emit = defineEmits<{ open: [id: string]; remove: [] }>()
</script>

<template>
  <div class="group/rel flex h-8 items-center gap-2 rounded-md px-2 text-[14px] hover:bg-row-hover" @click="emit('open', id)">
    <template v-if="row">
      <PriorityIcon :priority="row.priority" />
      <StatusIcon :tone="toneOf(row.status, statuses)" />
    </template>
    <span class="shrink-0 text-[13px] tabular-nums text-muted-foreground">{{ id }}</span>
    <span class="min-w-0 truncate" :class="{ 'text-muted-foreground': !row }">{{ row?.title ?? '' }}</span>
    <span v-if="note" class="shrink-0 text-[12.5px] text-subtle">{{ note }}</span>
    <button
      v-if="removable"
      type="button"
      class="ml-auto grid size-6 shrink-0 place-items-center rounded text-muted-foreground opacity-0 hover:bg-accent hover:text-foreground group-hover/rel:opacity-100"
      :aria-label="t('relations.remove')"
      @click.stop="emit('remove')"
    >
      <X class="size-3.5" />
    </button>
  </div>
</template>
