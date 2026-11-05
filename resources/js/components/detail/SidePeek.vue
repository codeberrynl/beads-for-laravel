<script setup lang="ts">
import IssueDetail from '@/components/detail/IssueDetail.vue'
import { usePeek } from '@/composables/usePeek'
import { useShortcut } from '@/composables/useShortcut'

/** Non-modal side panel: the list or board stays usable next to it. */
const peek = usePeek()

useShortcut('escape', () => {
  if (!peek.peekId.value) return false
  peek.close()
})
</script>

<template>
  <Transition
    enter-active-class="transition duration-150 ease-out"
    enter-from-class="translate-x-4 opacity-0"
    leave-active-class="transition duration-100 ease-in"
    leave-to-class="translate-x-4 opacity-0"
  >
    <section
      v-if="peek.peekId.value"
      class="absolute inset-y-2 right-2 z-30 flex w-[min(640px,calc(100%-1rem))] flex-col overflow-hidden rounded-lg border bg-background shadow-2xl shadow-black/30"
      :aria-label="peek.peekId.value"
    >
      <IssueDetail :id="peek.peekId.value" :key="peek.peekId.value" mode="peek" />
    </section>
  </Transition>
</template>
