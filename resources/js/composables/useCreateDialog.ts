import { reactive } from 'vue'

export interface CreatePrefill {
  status?: string
  priority?: number
  type?: string
  assignee?: string | null
  parent?: string | null
  labels?: string[]
}

/** Shared state of the create-issue modal (opened with C, or from context). */
const state = reactive<{ open: boolean; prefill: CreatePrefill }>({ open: false, prefill: {} })

export function useCreateDialog() {
  return {
    state,
    open: (prefill: CreatePrefill = {}) => {
      state.prefill = prefill
      state.open = true
    },
    close: () => {
      state.open = false
    },
  }
}
