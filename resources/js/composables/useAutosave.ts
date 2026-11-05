import { onBeforeUnmount, ref } from 'vue'
import { ApiError } from '@/lib/api'

export type SaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error' | 'conflict'

export interface Conflict {
  theirs: string
  mine: string
}

/**
 * Saves after 2 s without typing, on blur and on Cmd+Enter. A 409 becomes a
 * conflict (shown by the conflict bar) instead of an error toast.
 */
export function useAutosave(save: () => Promise<void> | void, delay = 2000) {
  const state = ref<SaveState>('idle')
  const conflict = ref<Conflict | null>(null)
  let timer: ReturnType<typeof setTimeout> | null = null
  let running: Promise<void> | null = null

  function cancel() {
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
  }

  function touch() {
    if (state.value !== 'conflict') {
      state.value = 'dirty'
    }
    cancel()
    timer = setTimeout(() => void flush(), delay)
  }

  async function flush(): Promise<void> {
    cancel()

    if (running) {
      await running
    }

    running = (async () => {
      try {
        await save()
      } catch (error) {
        if (!(error instanceof ApiError && error.isConflict)) {
          state.value = 'error'
        }
      } finally {
        running = null
      }
    })()

    await running
  }

  onBeforeUnmount(() => {
    if (timer) {
      void flush()
    }
  })

  return { state, conflict, touch, flush, cancel }
}
