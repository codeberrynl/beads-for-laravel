<script setup lang="ts">
import { ref } from 'vue'
import MarkdownView from '@/components/editor/MarkdownView.vue'
import UserAvatar from '@/components/badges/UserAvatar.vue'
import AgentBadge from '@/components/badges/AgentBadge.vue'
import { Button } from '@/components/ui/button'
import { useIssueActions } from '@/composables/useIssueActions'
import { useWorkspace } from '@/composables/useWorkspace'
import { fullDate, relativeTime } from '@/lib/time'
import { t } from '@/lib/i18n'
import type { Issue } from '@/lib/types'

const props = defineProps<{ issue: Issue }>()

const actions = useIssueActions()
const { actor, isHuman } = useWorkspace()

const text = ref('')
const sending = ref(false)

async function submit() {
  const value = text.value.trim()
  if (value === '' || sending.value) return

  sending.value = true
  text.value = ''

  try {
    await actions.comment(props.issue.id, value, actor.value)
  } catch {
    text.value = value
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <section class="mt-8 border-t pt-6">
    <h2 class="mb-3 text-[13px] font-medium text-muted-foreground">{{ t('comments.title') }}</h2>

    <p v-if="!issue.comments.length" class="mb-4 text-[13.5px] text-subtle">{{ t('comments.empty') }}</p>

    <ol class="space-y-3">
      <li v-for="comment in issue.comments" :key="comment.id" class="rounded-lg border bg-card px-3.5 py-2.5" :class="{ 'opacity-60': comment.id.startsWith('pending-') }">
        <div class="mb-1 flex items-center gap-2 text-[13px]">
          <UserAvatar :name="comment.author || null" :agent="!isHuman(comment.author)" :size="18" />
          <span class="font-medium">{{ comment.author || t('app.unknown') }}</span>
          <AgentBadge v-if="!isHuman(comment.author)" :name="comment.author" />
          <span class="text-subtle" :title="fullDate(comment.created_at)">{{ relativeTime(comment.created_at) }}</span>
        </div>
        <MarkdownView :markdown="comment.text" />
      </li>
    </ol>

    <form class="mt-4 rounded-lg border bg-card focus-within:border-ring" @submit.prevent="submit">
      <textarea
        v-model="text"
        rows="3"
        :placeholder="t('comments.placeholder')"
        :aria-label="t('comments.placeholder')"
        class="block w-full resize-y bg-transparent px-3.5 py-2.5 text-[14px] outline-none placeholder:text-subtle"
        @keydown.meta.enter.prevent="submit"
        @keydown.ctrl.enter.prevent="submit"
      />
      <div class="flex items-center justify-end gap-2 px-2.5 pb-2">
        <span class="text-[12.5px] text-subtle">{{ t('comments.submitHint') }}</span>
        <Button type="submit" size="xs" :disabled="text.trim() === '' || sending">{{ t('comments.submit') }}</Button>
      </div>
    </form>
  </section>
</template>
