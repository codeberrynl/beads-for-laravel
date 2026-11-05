import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { parseQuery, toQuery, type DisplayOptions, type ListFilters } from '@/lib/filters'

/** Filters and display options, read from and written to the URL query. */
export function useListState() {
  const route = useRoute()
  const router = useRouter()

  const state = computed(() => parseQuery(route.query))
  const filters = computed(() => state.value.filters)
  const display = computed(() => state.value.display)

  function replace(nextFilters: ListFilters, nextDisplay: DisplayOptions) {
    void router.replace({ query: toQuery(nextFilters, nextDisplay, route.query) })
  }

  return {
    filters,
    display,
    setFilters: (patch: Partial<ListFilters>) => replace({ ...filters.value, ...patch }, display.value),
    setDisplay: (patch: Partial<DisplayOptions>) => replace(filters.value, { ...display.value, ...patch }),
    clearFilters: () => replace({ status: [], priority: [], type: [], label: [], assignee: [], parent: [], q: '' }, display.value),
    toggle: <K extends 'status' | 'priority' | 'type' | 'label' | 'assignee' | 'parent'>(key: K, value: ListFilters[K][number]) => {
      const current = filters.value[key] as Array<typeof value>
      const next = current.includes(value) ? current.filter((item) => item !== value) : [...current, value]
      replace({ ...filters.value, [key]: next }, display.value)
    },
  }
}
