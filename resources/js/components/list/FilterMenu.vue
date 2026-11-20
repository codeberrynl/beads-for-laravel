<script setup lang="ts">
import { computed } from 'vue'
import { ListFilter } from '@lucide/vue'
import type { IssueRow, StatusDefinition, TypeDefinition } from '@/lib/types'
import { NO_PARENT, UNASSIGNED, type ListFilters } from '@/lib/filters'
import { PRIORITIES, priorityLabel } from '@/lib/priority'
import { statusLabel, toneOf } from '@/lib/status'
import { typeLabel } from '@/lib/types-meta'
import { t } from '@/lib/i18n'
import StatusIcon from '@/components/icons/StatusIcon.vue'
import PriorityIcon from '@/components/icons/PriorityIcon.vue'
import TypeIcon from '@/components/icons/TypeIcon.vue'
import UserAvatar from '@/components/badges/UserAvatar.vue'
import { labelColor } from '@/lib/colors'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

type Key = 'status' | 'priority' | 'type' | 'label' | 'assignee' | 'parent'

const props = defineProps<{
  filters: ListFilters
  rows: IssueRow[]
  statuses: StatusDefinition[]
  types: TypeDefinition[]
  byId: Map<string, IssueRow>
}>()

const emit = defineEmits<{ toggle: [key: Key, value: string | number] }>()

const labels = computed(() => [...new Set(props.rows.flatMap((row) => row.labels))].sort())
const assignees = computed(() => [...new Set(props.rows.map((row) => row.assignee).filter((name): name is string => !!name))].sort())
const parents = computed(() =>
  [...new Set(props.rows.map((row) => row.parent).filter((id): id is string => !!id))].map((id) => ({ id, title: props.byId.get(id)?.title ?? id })),
)

const checked = (key: Key, value: string | number) => (props.filters[key] as Array<string | number>).includes(value)
const count = (key: Key) => props.filters[key].length
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" size="sm" class="h-7 gap-1.5 px-2 text-[14px] text-muted-foreground">
        <ListFilter class="size-3.5" />
        {{ t('filters.filter') }}
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-52">
      <DropdownMenuSub>
        <DropdownMenuSubTrigger>{{ t('filters.status') }} <span v-if="count('status')" class="ml-auto text-xs text-muted-foreground">{{ count('status') }}</span></DropdownMenuSubTrigger>
        <DropdownMenuSubContent class="max-h-80 w-52 overflow-y-auto">
          <DropdownMenuCheckboxItem
            v-for="status in statuses"
            :key="status.name"
            :model-value="checked('status', status.name)"
            @select.prevent
            @update:model-value="emit('toggle', 'status', status.name)"
          >
            <StatusIcon :tone="toneOf(status.name, statuses)" /> {{ statusLabel(status.name) }}
          </DropdownMenuCheckboxItem>
        </DropdownMenuSubContent>
      </DropdownMenuSub>

      <DropdownMenuSub>
        <DropdownMenuSubTrigger>{{ t('filters.priority') }} <span v-if="count('priority')" class="ml-auto text-xs text-muted-foreground">{{ count('priority') }}</span></DropdownMenuSubTrigger>
        <DropdownMenuSubContent class="w-48">
          <DropdownMenuCheckboxItem
            v-for="priority in PRIORITIES"
            :key="priority"
            :model-value="checked('priority', priority)"
            @select.prevent
            @update:model-value="emit('toggle', 'priority', priority)"
          >
            <PriorityIcon :priority="priority" /> {{ priorityLabel(priority) }}
            <span class="ml-auto text-xs text-subtle">P{{ priority }}</span>
          </DropdownMenuCheckboxItem>
        </DropdownMenuSubContent>
      </DropdownMenuSub>

      <DropdownMenuSub>
        <DropdownMenuSubTrigger>{{ t('filters.type') }} <span v-if="count('type')" class="ml-auto text-xs text-muted-foreground">{{ count('type') }}</span></DropdownMenuSubTrigger>
        <DropdownMenuSubContent class="max-h-80 w-48 overflow-y-auto">
          <DropdownMenuCheckboxItem
            v-for="type in types"
            :key="type.name"
            :model-value="checked('type', type.name)"
            @select.prevent
            @update:model-value="emit('toggle', 'type', type.name)"
          >
            <TypeIcon :type="type.name" /> {{ typeLabel(type.name) }}
          </DropdownMenuCheckboxItem>
        </DropdownMenuSubContent>
      </DropdownMenuSub>

      <DropdownMenuSub>
        <DropdownMenuSubTrigger>{{ t('filters.label') }} <span v-if="count('label')" class="ml-auto text-xs text-muted-foreground">{{ count('label') }}</span></DropdownMenuSubTrigger>
        <DropdownMenuSubContent class="max-h-80 w-52 overflow-y-auto">
          <div v-if="!labels.length" class="px-2 py-1.5 text-xs text-muted-foreground">{{ t('labels.none') }}</div>
          <DropdownMenuCheckboxItem
            v-for="label in labels"
            :key="label"
            :model-value="checked('label', label)"
            @select.prevent
            @update:model-value="emit('toggle', 'label', label)"
          >
            <span class="size-2 rounded-full" :style="{ background: labelColor(label) }" /> {{ label }}
          </DropdownMenuCheckboxItem>
        </DropdownMenuSubContent>
      </DropdownMenuSub>

      <DropdownMenuSub>
        <DropdownMenuSubTrigger>{{ t('filters.assignee') }} <span v-if="count('assignee')" class="ml-auto text-xs text-muted-foreground">{{ count('assignee') }}</span></DropdownMenuSubTrigger>
        <DropdownMenuSubContent class="max-h-80 w-52 overflow-y-auto">
          <DropdownMenuCheckboxItem :model-value="checked('assignee', UNASSIGNED)" @select.prevent @update:model-value="emit('toggle', 'assignee', UNASSIGNED)">
            <UserAvatar :name="null" :size="16" /> {{ t('filters.unassigned') }}
          </DropdownMenuCheckboxItem>
          <DropdownMenuCheckboxItem
            v-for="name in assignees"
            :key="name"
            :model-value="checked('assignee', name)"
            @select.prevent
            @update:model-value="emit('toggle', 'assignee', name)"
          >
            <UserAvatar :name="name" :size="16" /> {{ name }}
          </DropdownMenuCheckboxItem>
        </DropdownMenuSubContent>
      </DropdownMenuSub>

      <DropdownMenuSub>
        <DropdownMenuSubTrigger>{{ t('filters.parent') }} <span v-if="count('parent')" class="ml-auto text-xs text-muted-foreground">{{ count('parent') }}</span></DropdownMenuSubTrigger>
        <DropdownMenuSubContent class="max-h-80 w-64 overflow-y-auto">
          <DropdownMenuCheckboxItem :model-value="checked('parent', NO_PARENT)" @select.prevent @update:model-value="emit('toggle', 'parent', NO_PARENT)">
            {{ t('filters.noParent') }}
          </DropdownMenuCheckboxItem>
          <DropdownMenuCheckboxItem
            v-for="parent in parents"
            :key="parent.id"
            :model-value="checked('parent', parent.id)"
            @select.prevent
            @update:model-value="emit('toggle', 'parent', parent.id)"
          >
            <span class="truncate">{{ parent.title }}</span>
            <span class="ml-auto shrink-0 text-xs text-subtle">{{ parent.id }}</span>
          </DropdownMenuCheckboxItem>
        </DropdownMenuSubContent>
      </DropdownMenuSub>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
