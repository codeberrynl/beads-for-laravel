<script setup lang="ts">
import { computed, toRef } from 'vue'
import { RouterLink } from 'vue-router'
import { ChevronRight, Link2, Maximize2, X } from '@lucide/vue'
import IssueProperties from '@/components/detail/IssueProperties.vue'
import TextSections from '@/components/detail/TextSections.vue'
import TitleField from '@/components/editor/TitleField.vue'
import AgentBadge from '@/components/badges/AgentBadge.vue'
import BlockedIndicator from '@/components/badges/BlockedIndicator.vue'
import TypeIcon from '@/components/icons/TypeIcon.vue'
import { useIssueDetail } from '@/composables/useBeads'
import { usePeek } from '@/composables/usePeek'
import { config } from '@/lib/config'
import { fullDate, relativeTime } from '@/lib/time'
import { t } from '@/lib/i18n'
import { toast } from 'vue-sonner'
import { copyText } from '@/lib/clipboard'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'

const props = defineProps<{ id: string; mode: 'peek' | 'page' }>()

const detail = useIssueDetail(toRef(props, 'id'))
const peek = usePeek()

const payload = computed(() => detail.data.value)
const issue = computed(() => (payload.value?.issue.id === props.id ? payload.value.issue : undefined))
const related = computed(() => payload.value?.issues ?? {})
const parent = computed(() => (issue.value?.parent ? related.value[issue.value.parent] : undefined))

async function copyLink() {
  await copyText(`${location.origin}${config.basePath}/issue/${encodeURIComponent(props.id)}`)
  toast.success(t('app.copied'))
}

async function copyId() {
  await copyText(props.id)
  toast.success(t('app.copied'))
}
</script>

<template>
  <div class="flex h-full min-h-0 flex-col">
    <header class="flex h-11 shrink-0 items-center gap-2 border-b px-4 text-[13px]">
      <RouterLink v-if="mode === 'page'" to="/issues" class="text-muted-foreground hover:text-foreground">{{ t('nav.issues') }}</RouterLink>
      <ChevronRight v-if="mode === 'page'" class="size-3.5 text-subtle" />
      <template v-if="parent">
        <button type="button" class="flex min-w-0 items-center gap-1.5 text-muted-foreground hover:text-foreground" @click="peek.open(parent.id)">
          <TypeIcon :type="parent.type" :size="13" />
          <span class="max-w-48 truncate">{{ parent.title }}</span>
        </button>
        <ChevronRight class="size-3.5 text-subtle" />
      </template>
      <button type="button" class="tabular-nums text-muted-foreground hover:text-foreground" :title="t('detail.copyId')" @click="copyId">{{ id }}</button>

      <div class="ml-auto flex items-center gap-0.5">
        <slot name="actions" :issue="issue" />
        <Tooltip>
          <TooltipTrigger as-child>
            <button type="button" class="grid size-7 place-items-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground" :aria-label="t('detail.copyLink')" @click="copyLink">
              <Link2 class="size-4" />
            </button>
          </TooltipTrigger>
          <TooltipContent>{{ t('detail.copyLink') }}</TooltipContent>
        </Tooltip>
        <template v-if="mode === 'peek'">
          <Tooltip>
            <TooltipTrigger as-child>
              <button type="button" class="grid size-7 place-items-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground" :aria-label="t('detail.openFullPage')" @click="peek.openPage(id)">
                <Maximize2 class="size-3.5" />
              </button>
            </TooltipTrigger>
            <TooltipContent>{{ t('detail.openFullPage') }}</TooltipContent>
          </Tooltip>
          <Tooltip>
            <TooltipTrigger as-child>
              <button type="button" class="grid size-7 place-items-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground" :aria-label="t('detail.closePeek')" @click="peek.close()">
                <X class="size-4" />
              </button>
            </TooltipTrigger>
            <TooltipContent>{{ t('detail.closePeek') }} <span class="ml-1 text-subtle">Esc</span></TooltipContent>
          </Tooltip>
        </template>
      </div>
    </header>

    <div v-if="detail.isPending.value" class="p-6 text-[13px] text-muted-foreground">{{ t('app.loading') }}</div>

    <div v-else-if="!issue" class="grid flex-1 place-items-center p-6 text-center">
      <div>
        <p class="text-[14px] font-medium">{{ t('detail.notFound') }}</p>
        <p class="mt-1 text-[13px] text-muted-foreground">{{ t('detail.notFoundHint') }}</p>
        <RouterLink to="/issues" class="mt-3 inline-block text-[13px] text-primary hover:underline">{{ t('detail.back') }}</RouterLink>
      </div>
    </div>

    <div v-else class="min-h-0 flex-1 overflow-y-auto" :class="mode === 'page' ? 'flex' : ''">
      <div class="min-w-0 flex-1" :class="mode === 'page' ? 'mx-auto max-w-3xl px-10 py-8' : 'px-6 py-5'">
        <div class="mb-1 flex items-center gap-2">
          <AgentBadge v-if="!issue.author_is_human && issue.created_by" :name="issue.created_by" />
          <BlockedIndicator v-if="issue.blocked_by.length" :blocked-by="issue.blocked_by" />
        </div>

        <TitleField :key="`${issue.id}-title`" :issue-id="issue.id" :value="issue.title" />

        <div v-if="mode === 'peek'" class="mt-1 rounded-lg border px-3 py-2">
          <IssueProperties :issue="issue" :related="related" />
        </div>

        <TextSections :issue="issue" />

        <slot name="after" :issue="issue" :payload="payload" />

        <p class="mt-8 text-[12px] text-subtle">
          <span :title="fullDate(issue.created_at)">{{ t('detail.created', { date: relativeTime(issue.created_at), name: issue.created_by ?? t('app.unknown') }) }}</span>
          · <span :title="fullDate(issue.updated_at)">{{ t('detail.updated', { date: relativeTime(issue.updated_at) }) }}</span>
          <template v-if="issue.closed_at"> · <span :title="fullDate(issue.closed_at)">{{ t('detail.closed', { date: relativeTime(issue.closed_at) }) }}</span></template>
        </p>
      </div>

      <aside v-if="mode === 'page'" class="w-72 shrink-0 border-l px-4 py-6">
        <div class="mb-2 text-[11px] font-medium text-subtle">{{ t('detail.properties') }}</div>
        <IssueProperties :issue="issue" :related="related" />
      </aside>
    </div>
  </div>
</template>
