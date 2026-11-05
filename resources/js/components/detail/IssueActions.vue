<script setup lang="ts">
import { computed, ref } from 'vue'
import { toast } from 'vue-sonner'
import { CircleCheck, CircleSlash, Copy, Ellipsis, MessageSquareText, RotateCcw } from '@lucide/vue'
import { useIssueActions } from '@/composables/useIssueActions'
import { useWorkspace } from '@/composables/useWorkspace'
import { isDone } from '@/lib/status'
import { copyText } from '@/lib/clipboard'
import { t } from '@/lib/i18n'
import type { Issue } from '@/lib/types'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'

/**
 * Close (directly, no dialog), Close with reason…, Reopen, and Force close
 * (pinned issues and gates only, after a confirmation).
 */
const props = defineProps<{ issue: Issue }>()

const actions = useIssueActions()
const { statuses } = useWorkspace()

const done = computed(() => isDone(props.issue.status, statuses.value))
const canForce = computed(() => !done.value && (props.issue.status === 'pinned' || props.issue.type === 'gate'))

const reasonOpen = ref(false)
const reason = ref('')
const forceOpen = ref(false)

function close(withReason?: string, force = false) {
  void actions
    .close(props.issue.id, withReason, force)
    .then(() => toast.success(t('actions.closed', { id: props.issue.id })))
    .catch(() => undefined)
}

function reopen() {
  void actions
    .reopen(props.issue.id)
    .then(() => toast.success(t('actions.reopened', { id: props.issue.id })))
    .catch(() => undefined)
}

function submitReason() {
  reasonOpen.value = false
  close(reason.value.trim() || undefined)
  reason.value = ''
}

async function copyId() {
  await copyText(props.issue.id)
  toast.success(t('app.copied'))
}
</script>

<template>
  <div class="flex items-center gap-0.5">
    <Button v-if="!done && !canForce" variant="ghost" size="sm" class="h-7 gap-1.5 px-2 text-[12.5px] text-muted-foreground" @click="close()">
      <CircleCheck class="size-3.5" /> {{ t('actions.close') }}
    </Button>
    <Button v-if="done" variant="ghost" size="sm" class="h-7 gap-1.5 px-2 text-[12.5px] text-muted-foreground" @click="reopen">
      <RotateCcw class="size-3.5" /> {{ t('actions.reopen') }}
    </Button>

    <DropdownMenu>
      <DropdownMenuTrigger as-child>
        <button type="button" class="grid size-7 place-items-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground" :aria-label="t('detail.actions')">
          <Ellipsis class="size-4" />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" class="w-52">
        <template v-if="!done">
          <DropdownMenuItem v-if="!canForce" @select="close()"><CircleCheck /> {{ t('actions.close') }}</DropdownMenuItem>
          <DropdownMenuItem @select="reasonOpen = true"><MessageSquareText /> {{ t('actions.closeWithReason') }}</DropdownMenuItem>
          <DropdownMenuItem v-if="canForce" class="text-destructive focus:text-destructive" @select="forceOpen = true"><CircleSlash /> {{ t('actions.forceClose') }}</DropdownMenuItem>
        </template>
        <DropdownMenuItem v-else @select="reopen"><RotateCcw /> {{ t('actions.reopen') }}</DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem @select="copyId"><Copy /> {{ t('detail.copyId') }}</DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>

    <Dialog v-model:open="reasonOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{{ t('actions.closeWithReason') }}</DialogTitle>
          <DialogDescription>{{ issue.id }} · {{ issue.title }}</DialogDescription>
        </DialogHeader>
        <textarea
          v-model="reason"
          rows="4"
          :placeholder="t('actions.reasonPlaceholder')"
          class="w-full resize-y rounded-md border bg-transparent px-3 py-2 text-[13px] outline-none focus:border-ring"
          @keydown.meta.enter.prevent="submitReason"
          @keydown.ctrl.enter.prevent="submitReason"
        />
        <DialogFooter>
          <Button variant="ghost" size="sm" @click="reasonOpen = false">{{ t('app.cancel') }}</Button>
          <Button size="sm" @click="submitReason">{{ t('actions.close') }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="forceOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{{ t('actions.forceCloseTitle') }}</AlertDialogTitle>
          <AlertDialogDescription>{{ t('actions.forceCloseBody', { id: issue.id }) }}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{{ t('app.cancel') }}</AlertDialogCancel>
          <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="close(undefined, true)">{{ t('actions.forceClose') }}</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
