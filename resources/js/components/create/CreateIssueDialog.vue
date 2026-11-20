<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import { Plus, X } from '@lucide/vue'
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Kbd } from '@/components/ui/kbd'
import OptionPicker from '@/components/pickers/OptionPicker.vue'
import MarkdownInput from '@/components/editor/MarkdownInput.vue'
import StatusIcon from '@/components/icons/StatusIcon.vue'
import PriorityIcon from '@/components/icons/PriorityIcon.vue'
import TypeIcon from '@/components/icons/TypeIcon.vue'
import UserAvatar from '@/components/badges/UserAvatar.vue'
import LabelDot from '@/components/badges/LabelDot.vue'
import { useCreateDialog } from '@/composables/useCreateDialog'
import { usePickerOptions } from '@/composables/usePickerOptions'
import { useIssueActions } from '@/composables/useIssueActions'
import { useWorkspace } from '@/composables/useWorkspace'
import { useSnapshot } from '@/composables/useBeads'
import { usePeek } from '@/composables/usePeek'
import { statusLabel, toneOf } from '@/lib/status'
import { priorityLabel } from '@/lib/priority'
import { typeLabel } from '@/lib/types-meta'
import { t } from '@/lib/i18n'

const dialog = useCreateDialog()
const actions = useIssueActions()
const snapshot = useSnapshot()
const peek = usePeek()
const { statuses, isHuman } = useWorkspace()
const { statusOptions, priorityOptions, typeOptions, assigneeOptions, labelOptions, parentOptions } = usePickerOptions()

const defaultStatus = computed(() => statuses.value.find((status) => status.name === 'open')?.name ?? statuses.value.find((status) => status.category === 'active')?.name ?? 'open')

const form = reactive({
  title: '',
  description: '',
  status: 'open',
  priority: 2,
  type: 'task',
  assignee: null as string | null,
  labels: [] as string[],
  parent: null as string | null,
})

const createMore = ref(false)
const submitting = ref(false)
const titleInput = ref<HTMLTextAreaElement | null>(null)

/** Fill the form from the context the modal was opened from. */
function reset(keepProperties = false) {
  const prefill = dialog.state.prefill

  form.title = ''
  form.description = ''

  if (!keepProperties) {
    form.status = prefill.status ?? defaultStatus.value
    form.priority = prefill.priority ?? 2
    form.type = prefill.type ?? 'task'
    form.assignee = prefill.assignee ?? null
    form.labels = [...(prefill.labels ?? [])]
    form.parent = prefill.parent ?? null
  }
}

watch(
  () => dialog.state.open,
  async (open) => {
    if (open) {
      reset()
      await nextTick()
      titleInput.value?.focus()
    }
  },
)

const parentRow = computed(() => (form.parent ? snapshot.byId.value.get(form.parent) : undefined))
const canSubmit = computed(() => form.title.trim() !== '' && !submitting.value)

function toggleLabel(label: string) {
  form.labels = form.labels.includes(label) ? form.labels.filter((item) => item !== label) : [...form.labels, label]
}

async function submit() {
  if (!canSubmit.value) return

  submitting.value = true

  try {
    const payload = await actions.create({
      title: form.title.trim(),
      description: form.description || undefined,
      status: form.status,
      priority: form.priority,
      type: form.type,
      assignee: form.assignee,
      labels: form.labels,
      parent: form.parent,
    })
    const id = payload.issue.id

    toast.success(t('create.created', { id }), { action: { label: t('create.view'), onClick: () => peek.open(id) } })

    if (createMore.value) {
      reset(true)
      await nextTick()
      titleInput.value?.focus()
    } else {
      dialog.close()
    }
  } catch {
    // The toast came from the action; the form keeps its input.
  } finally {
    submitting.value = false
  }
}

function resize(event: Event) {
  const element = event.target as HTMLTextAreaElement
  element.style.height = 'auto'
  element.style.height = `${element.scrollHeight}px`
}

const chip = 'inline-flex h-7 items-center gap-1.5 rounded-md border px-2 text-[13.5px] hover:bg-accent data-[state=open]:bg-accent'
</script>

<template>
  <Dialog :open="dialog.state.open" @update:open="(open) => (open ? undefined : dialog.close())">
    <DialogContent class="top-[12%] max-w-2xl translate-y-0 gap-0 p-0 sm:max-w-2xl" :show-close-button="false" @keydown.meta.enter.prevent="submit" @keydown.ctrl.enter.prevent="submit">
      <div class="flex items-center gap-2 px-5 pt-4 text-[13.5px] text-muted-foreground">
        <span v-if="parentRow" class="inline-flex max-w-72 items-center gap-1.5 truncate rounded-md border px-2 py-0.5">
          <TypeIcon :type="parentRow.type" :size="12" /> {{ t('create.in', { parent: parentRow.title }) }}
        </span>
        <DialogTitle class="text-[13.5px] font-medium text-foreground">{{ t('create.title') }}</DialogTitle>
        <DialogDescription class="sr-only">{{ t('create.title') }}</DialogDescription>
        <button type="button" class="ml-auto grid size-6 place-items-center rounded hover:bg-accent hover:text-foreground" :aria-label="t('app.close')" @click="dialog.close()">
          <X class="size-4" />
        </button>
      </div>

      <div class="px-5 pt-3">
        <textarea
          ref="titleInput"
          v-model="form.title"
          rows="1"
          :placeholder="t('create.titlePlaceholder')"
          :aria-label="t('create.titlePlaceholder')"
          class="block w-full resize-none overflow-hidden bg-transparent text-[19px] font-semibold outline-none placeholder:text-subtle"
          @input="resize"
          @keydown.enter.exact.prevent
        />
        <div class="mt-2 max-h-[40vh] overflow-y-auto pb-3">
          <MarkdownInput v-model="form.description" :placeholder="t('create.descriptionPlaceholder')" @submit="submit" />
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-1.5 px-5 pb-4">
        <OptionPicker :options="statusOptions" :selected="[form.status]" :placeholder="t('status.change')" @select="(value) => (form.status = value)">
          <button type="button" :class="chip"><StatusIcon :tone="toneOf(form.status, statuses)" /> {{ statusLabel(form.status) }}</button>
        </OptionPicker>
        <OptionPicker :options="priorityOptions" :selected="[form.priority]" :placeholder="t('priority.change')" @select="(value) => (form.priority = value)">
          <button type="button" :class="chip"><PriorityIcon :priority="form.priority" /> {{ priorityLabel(form.priority) }}</button>
        </OptionPicker>
        <OptionPicker :options="typeOptions" :selected="[form.type]" :placeholder="t('type.change')" @select="(value) => (form.type = value)">
          <button type="button" :class="chip"><TypeIcon :type="form.type" /> {{ typeLabel(form.type) }}</button>
        </OptionPicker>
        <OptionPicker
          :options="assigneeOptions"
          :selected="[form.assignee]"
          :placeholder="t('assignee.change')"
          creatable
          :create-label="(name) => t('assignee.assignTo', { name })"
          @select="(value) => (form.assignee = value)"
          @create="(value) => (form.assignee = value)"
        >
          <button type="button" :class="chip">
            <UserAvatar :name="form.assignee" :agent="form.assignee !== null && !isHuman(form.assignee)" :size="16" />
            {{ form.assignee ?? t('assignee.none') }}
          </button>
        </OptionPicker>
        <OptionPicker
          :options="labelOptions"
          :selected="form.labels"
          multiple
          creatable
          :create-label="(name) => t('labels.create', { name })"
          :placeholder="t('labels.change')"
          @select="toggleLabel"
          @create="toggleLabel"
        >
          <button type="button" :class="chip">
            <template v-if="form.labels.length">
              <LabelDot v-for="label in form.labels.slice(0, 3)" :key="label" :label="label" />
              {{ form.labels.length === 1 ? form.labels[0] : `${form.labels.length} ${t('labels.label').toLowerCase()}` }}
            </template>
            <template v-else><Plus class="size-3" /> {{ t('labels.label') }}</template>
          </button>
        </OptionPicker>
        <OptionPicker :options="parentOptions(null)" :selected="[form.parent]" :placeholder="t('parent.change')" width="20rem" @select="(value) => (form.parent = value)">
          <button type="button" :class="chip" class="max-w-56">
            <span class="truncate">{{ parentRow?.title ?? t('parent.label') }}</span>
          </button>
        </OptionPicker>
      </div>

      <div class="flex items-center gap-3 border-t px-5 py-3">
        <label class="flex items-center gap-2 text-[13.5px] text-muted-foreground">
          <Checkbox :model-value="createMore" @update:model-value="(value) => (createMore = value === true)" />
          {{ t('create.createMore') }}
        </label>
        <Button class="ml-auto gap-2" size="sm" :disabled="!canSubmit" @click="submit">
          {{ t('create.submit') }}
          <Kbd class="bg-primary-foreground/15 text-primary-foreground">⌘↵</Kbd>
        </Button>
      </div>
    </DialogContent>
  </Dialog>
</template>
