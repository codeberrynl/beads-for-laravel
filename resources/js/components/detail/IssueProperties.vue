<script setup lang="ts">
import { computed } from 'vue'
import { Plus } from '@lucide/vue'
import OptionPicker from '@/components/pickers/OptionPicker.vue'
import StatusIcon from '@/components/icons/StatusIcon.vue'
import PriorityIcon from '@/components/icons/PriorityIcon.vue'
import TypeIcon from '@/components/icons/TypeIcon.vue'
import UserAvatar from '@/components/badges/UserAvatar.vue'
import LabelChip from '@/components/badges/LabelChip.vue'
import { usePickerOptions } from '@/composables/usePickerOptions'
import { useIssueActions } from '@/composables/useIssueActions'
import { useWorkspace } from '@/composables/useWorkspace'
import { useSnapshot } from '@/composables/useBeads'
import { isDone, statusLabel, toneOf } from '@/lib/status'
import { priorityLabel } from '@/lib/priority'
import { typeLabel } from '@/lib/types-meta'
import { t } from '@/lib/i18n'
import type { Issue, IssueRow } from '@/lib/types'
import type { UpdateFields } from '@/lib/api'

const props = defineProps<{ issue: Issue; related: Record<string, IssueRow> }>()

const { statuses, isHuman } = useWorkspace()
const snapshot = useSnapshot()
const actions = useIssueActions()
const { statusOptions, priorityOptions, typeOptions, assigneeOptions, labelOptions, parentOptions } = usePickerOptions()

const parentTitle = computed(() => {
  const parent = props.issue.parent
  return parent ? (props.related[parent]?.title ?? snapshot.byId.value.get(parent)?.title ?? parent) : null
})

/** Pickers write one field, directly and optimistically. */
function save(fields: UpdateFields) {
  void actions.update(props.issue.id, fields).catch(() => undefined)
}

/** A done status goes through bd close (closed_at, refusals), the rest is an update. */
function setStatus(status: string) {
  if (isDone(status, statuses.value) && status === 'closed') {
    void actions.close(props.issue.id).catch(() => undefined)
    return
  }
  save({ status })
}

function toggleLabel(label: string) {
  save(props.issue.labels.includes(label) ? { labels: { remove: [label] } } : { labels: { add: [label] } })
}

const rowClass = 'flex h-8 items-center gap-3'
const labelClass = 'w-20 shrink-0 text-[13px] text-muted-foreground'
const buttonClass = 'flex h-7 min-w-0 items-center gap-2 rounded-md px-1.5 text-[14px] hover:bg-accent data-[state=open]:bg-accent'
</script>

<template>
  <div class="space-y-0.5">
    <div :class="rowClass">
      <span :class="labelClass">{{ t('status.label') }}</span>
      <OptionPicker :options="statusOptions" :selected="[issue.status]" :placeholder="t('status.change')" @select="setStatus">
        <button type="button" :class="buttonClass"><StatusIcon :tone="toneOf(issue.status, statuses)" /> {{ statusLabel(issue.status) }}</button>
      </OptionPicker>
    </div>

    <div :class="rowClass">
      <span :class="labelClass">{{ t('priority.label') }}</span>
      <OptionPicker :options="priorityOptions" :selected="[issue.priority]" :placeholder="t('priority.change')" @select="(priority) => save({ priority })">
        <button type="button" :class="buttonClass"><PriorityIcon :priority="issue.priority" /> {{ priorityLabel(issue.priority) }}</button>
      </OptionPicker>
    </div>

    <div :class="rowClass">
      <span :class="labelClass">{{ t('type.label') }}</span>
      <OptionPicker :options="typeOptions" :selected="[issue.type]" :placeholder="t('type.change')" @select="(type) => save({ type })">
        <button type="button" :class="buttonClass"><TypeIcon :type="issue.type" /> {{ typeLabel(issue.type) }}</button>
      </OptionPicker>
    </div>

    <div :class="rowClass">
      <span :class="labelClass">{{ t('assignee.label') }}</span>
      <OptionPicker
        :options="assigneeOptions"
        :selected="[issue.assignee]"
        :placeholder="t('assignee.change')"
        creatable
        :create-label="(name) => t('assignee.assignTo', { name })"
        @select="(assignee) => save({ assignee })"
        @create="(assignee) => save({ assignee })"
      >
        <button type="button" :class="buttonClass">
          <UserAvatar :name="issue.assignee" :agent="issue.assignee !== null && !isHuman(issue.assignee)" :size="16" />
          <span :class="{ 'text-muted-foreground': !issue.assignee }" class="truncate">{{ issue.assignee ?? t('assignee.none') }}</span>
        </button>
      </OptionPicker>
    </div>

    <div class="flex min-h-8 items-start gap-3 py-0.5">
      <span :class="labelClass" class="pt-1.5">{{ t('labels.label') }}</span>
      <div class="flex min-w-0 flex-wrap items-center gap-1 pt-0.5">
        <LabelChip v-for="label in issue.labels" :key="label" :label="label" />
        <OptionPicker
          :options="labelOptions"
          :selected="issue.labels"
          multiple
          creatable
          :create-label="(name) => t('labels.create', { name })"
          :placeholder="t('labels.change')"
          @select="toggleLabel"
          @create="(label) => save({ labels: { add: [label] } })"
        >
          <button type="button" class="grid size-6 place-items-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground" :aria-label="t('labels.add')">
            <Plus class="size-3.5" />
          </button>
        </OptionPicker>
      </div>
    </div>

    <div :class="rowClass">
      <span :class="labelClass">{{ t('parent.label') }}</span>
      <OptionPicker :options="parentOptions(issue.id)" :selected="[issue.parent]" :placeholder="t('parent.change')" width="20rem" @select="(parent) => save({ parent })">
        <button type="button" :class="buttonClass">
          <span :class="{ 'text-muted-foreground': !parentTitle }" class="truncate">{{ parentTitle ?? t('parent.none') }}</span>
        </button>
      </OptionPicker>
    </div>
  </div>
</template>
