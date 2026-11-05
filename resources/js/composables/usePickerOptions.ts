import { computed } from 'vue'
import StatusIcon from '@/components/icons/StatusIcon.vue'
import PriorityIcon from '@/components/icons/PriorityIcon.vue'
import TypeIcon from '@/components/icons/TypeIcon.vue'
import UserAvatar from '@/components/badges/UserAvatar.vue'
import LabelDot from '@/components/badges/LabelDot.vue'
import type { PickerOption } from '@/components/pickers/OptionPicker.vue'
import { useSnapshot } from '@/composables/useBeads'
import { useWorkspace } from '@/composables/useWorkspace'
import { statusLabel, toneOf } from '@/lib/status'
import { PRIORITIES, priorityLabel } from '@/lib/priority'
import { typeLabel } from '@/lib/types-meta'
import { t } from '@/lib/i18n'

/** Options for the property pickers, from the definitions and the snapshot. */
export function usePickerOptions() {
  const snapshot = useSnapshot()
  const { statuses, selectableTypes, actor, humans, isHuman } = useWorkspace()

  const statusOptions = computed<PickerOption<string>[]>(() =>
    statuses.value.map((status) => ({ value: status.name, label: statusLabel(status.name), icon: StatusIcon, iconProps: { tone: toneOf(status.name, statuses.value) } })),
  )

  const priorityOptions = computed<PickerOption<number>[]>(() =>
    PRIORITIES.map((priority) => ({ value: priority, label: priorityLabel(priority), hint: `P${priority}`, keywords: `p${priority}`, icon: PriorityIcon, iconProps: { priority } })),
  )

  const typeOptions = computed<PickerOption<string>[]>(() =>
    selectableTypes.value.map((type) => ({ value: type.name, label: typeLabel(type.name), icon: TypeIcon, iconProps: { type: type.name } })),
  )

  const knownAssignees = computed(() => {
    const names = new Set<string>([actor.value, ...humans.value].filter(Boolean))
    for (const row of snapshot.rows.value) if (row.assignee) names.add(row.assignee)
    return [...names]
  })

  const assigneeOptions = computed<PickerOption<string | null>[]>(() => [
    { value: null, label: t('assignee.none'), icon: UserAvatar, iconProps: { name: null, size: 16 } },
    ...knownAssignees.value.map((name) => ({
      value: name,
      label: name === actor.value ? t('assignee.me', { name }) : name,
      icon: UserAvatar,
      iconProps: { name, size: 16, agent: !isHuman(name) },
    })),
  ])

  const labelOptions = computed<PickerOption<string>[]>(() =>
    [...new Set(snapshot.rows.value.flatMap((row) => row.labels))].sort().map((label) => ({ value: label, label, icon: LabelDot, iconProps: { label } })),
  )

  /** Issues that can be the parent of `id` (not itself, not its descendants). */
  function parentOptions(id: string | null): PickerOption<string | null>[] {
    const rows = snapshot.rows.value
    const excluded = new Set<string>(id ? [id] : [])

    let grew = true
    while (grew) {
      grew = false
      for (const row of rows) {
        if (row.parent && excluded.has(row.parent) && !excluded.has(row.id)) {
          excluded.add(row.id)
          grew = true
        }
      }
    }

    const candidates = rows
      .filter((row) => !excluded.has(row.id))
      .sort((a, b) => Number(b.type === 'epic') - Number(a.type === 'epic') || a.title.localeCompare(b.title))

    return [
      { value: null, label: t('parent.none') },
      ...candidates.map((row) => ({ value: row.id, label: row.title, hint: row.id, keywords: row.id, icon: TypeIcon, iconProps: { type: row.type } })),
    ]
  }

  return { statusOptions, priorityOptions, typeOptions, assigneeOptions, labelOptions, parentOptions }
}
