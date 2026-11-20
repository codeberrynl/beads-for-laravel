<script setup lang="ts">
import { computed } from 'vue'
import { Plus } from '@lucide/vue'
import RelationRow from '@/components/detail/RelationRow.vue'
import OptionPicker, { type PickerOption } from '@/components/pickers/OptionPicker.vue'
import TypeIcon from '@/components/icons/TypeIcon.vue'
import { useSnapshot } from '@/composables/useBeads'
import { useWorkspace } from '@/composables/useWorkspace'
import { useIssueActions } from '@/composables/useIssueActions'
import { usePeek } from '@/composables/usePeek'
import { useCreateDialog } from '@/composables/useCreateDialog'
import type { RelationInput } from '@/lib/api'
import { t } from '@/lib/i18n'
import type { Dependency, Issue, IssueRelations, IssueRow } from '@/lib/types'

/**
 * Editable: Blocked by / Blocking (blocks), Related (related), Sub-issues
 * (parent-child). Every other type is listed read-only, but can be removed.
 */
const props = defineProps<{ issue: Issue; relations: IssueRelations; issues: Record<string, IssueRow> }>()

const snapshot = useSnapshot()
const { statuses } = useWorkspace()
const actions = useIssueActions()
const peek = usePeek()
const createDialog = useCreateDialog()

const rowOf = (id: string): IssueRow | undefined => props.issues[id] ?? snapshot.byId.value.get(id)

function candidates(exclude: string[]): PickerOption<string>[] {
  const skip = new Set([props.issue.id, ...exclude])

  return snapshot.rows.value
    .filter((row) => !skip.has(row.id))
    .map((row) => ({ value: row.id, label: row.title, hint: row.id, keywords: row.id, icon: TypeIcon, iconProps: { type: row.type } }))
}

type Kind = 'blocked_by' | 'blocking' | 'related' | 'children'

const groups = computed(() => [
  { kind: 'blocked_by' as Kind, label: t('relations.blockedBy'), ids: props.relations.blocked_by, add: t('relations.addBlockedBy') },
  { kind: 'blocking' as Kind, label: t('relations.blocking'), ids: props.relations.blocking, add: t('relations.addBlocking') },
  { kind: 'related' as Kind, label: t('relations.related'), ids: props.relations.related, add: t('relations.addRelated') },
  { kind: 'children' as Kind, label: t('relations.subIssues'), ids: props.relations.children, add: t('relations.addSubIssue') },
])

function input(kind: Kind, target: string): RelationInput {
  switch (kind) {
    case 'blocked_by':
    case 'blocking':
      return { type: 'blocks', target, direction: kind }
    case 'related':
      return { type: 'related', target, direction: 'both' }
    default:
      return { type: 'parent-child', target, direction: 'child' }
  }
}

function add(kind: Kind, target: string) {
  void actions.addRelation(props.issue.id, input(kind, target)).catch(() => undefined)
}

function remove(kind: Kind, target: string) {
  void actions.removeRelation(props.issue.id, input(kind, target)).catch(() => undefined)
}

function otherNote(dependency: Dependency): { id: string; note: string; direction: 'incoming' | 'outgoing' } {
  const outgoing = dependency.issue_id === props.issue.id
  const id = outgoing ? dependency.depends_on_id : dependency.issue_id

  return { id, direction: outgoing ? 'outgoing' : 'incoming', note: outgoing ? t('relations.outgoing', { type: dependency.type, id }) : t('relations.incoming', { type: dependency.type, id }) }
}

function removeOther(dependency: Dependency) {
  const { id, direction } = otherNote(dependency)
  void actions.removeRelation(props.issue.id, { type: dependency.type, target: id, direction }).catch(() => undefined)
}
</script>

<template>
  <section class="mt-8">
    <h2 class="mb-2 text-[13px] font-medium text-muted-foreground">{{ t('relations.title') }}</h2>

    <div class="space-y-3">
      <div v-for="group in groups" :key="group.kind">
        <div class="flex h-6 items-center gap-1 text-[13px] text-subtle">
          <span>{{ group.label }}</span>
          <span v-if="group.ids.length" class="tabular-nums">{{ group.ids.length }}</span>
          <OptionPicker :options="candidates(group.ids)" :selected="[]" :placeholder="group.add" width="22rem" align="start" @select="(target) => add(group.kind, target)">
            <button type="button" class="ml-1 grid size-5 place-items-center rounded text-muted-foreground hover:bg-accent hover:text-foreground" :aria-label="group.add">
              <Plus class="size-3.5" />
            </button>
          </OptionPicker>
          <button
            v-if="group.kind === 'children'"
            type="button"
            class="ml-1 text-[12.5px] text-muted-foreground hover:text-foreground"
            @click="createDialog.open({ parent: issue.id })"
          >
            {{ t('relations.newSubIssue') }}
          </button>
        </div>
        <RelationRow
          v-for="id in group.ids"
          :id="id"
          :key="id"
          :row="rowOf(id)"
          :statuses="statuses"
          @open="peek.open"
          @remove="remove(group.kind, id)"
        />
      </div>

      <div v-if="relations.other.length">
        <div class="flex h-6 items-center text-[13px] text-subtle">{{ t('relations.other') }}</div>
        <RelationRow
          v-for="dependency in relations.other"
          :id="otherNote(dependency).id"
          :key="`${dependency.issue_id}-${dependency.depends_on_id}-${dependency.type}`"
          :row="rowOf(otherNote(dependency).id)"
          :statuses="statuses"
          :note="dependency.type"
          @open="peek.open"
          @remove="removeOther(dependency)"
        />
      </div>
    </div>
  </section>
</template>
