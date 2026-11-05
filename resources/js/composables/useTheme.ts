import { computed, ref, watch } from 'vue'

export type ThemeChoice = 'system' | 'light' | 'dark'

const STORAGE_KEY = 'beads-theme'
const media = window.matchMedia?.('(prefers-color-scheme: light)')

function read(): ThemeChoice {
  try {
    const value = localStorage.getItem(STORAGE_KEY)
    return value === 'light' || value === 'dark' || value === 'system' ? value : 'system'
  } catch {
    return 'system'
  }
}

const choice = ref<ThemeChoice>(read())
const systemLight = ref(media?.matches === true)

/** Dark-first: dark unless the choice (or the system) says light. */
function apply(): void {
  const light = choice.value === 'light' || (choice.value === 'system' && media?.matches === true)
  document.documentElement.classList.toggle('dark', !light)
}

watch(choice, (value) => {
  try {
    localStorage.setItem(STORAGE_KEY, value)
  } catch {
    // Private mode: the choice lasts for this page only.
  }
  apply()
})

media?.addEventListener('change', () => {
  systemLight.value = media.matches
  apply()
})
apply()

export function useTheme() {
  const order: ThemeChoice[] = ['system', 'light', 'dark']

  return {
    choice,
    resolved: computed<'light' | 'dark'>(() => (choice.value === 'light' || (choice.value === 'system' && systemLight.value) ? 'light' : 'dark')),
    setTheme: (value: ThemeChoice) => {
      choice.value = value
    },
    cycle: () => {
      choice.value = order[(order.indexOf(choice.value) + 1) % order.length] ?? 'system'
    },
  }
}
