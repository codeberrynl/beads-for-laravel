import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

/** The side-peek lives in the URL (?peek=ID), so it can be linked and closed with Back. */
export function usePeek() {
  const route = useRoute()
  const router = useRouter()

  const peekId = computed(() => (typeof route.query.peek === 'string' && route.query.peek !== '' ? route.query.peek : null))

  return {
    peekId,
    open: (id: string) => {
      if (route.name === 'issue') {
        void router.push({ name: 'issue', params: { id } })
        return
      }
      const query = { ...route.query, peek: id }
      void (route.query.peek ? router.replace({ query }) : router.push({ query }))
    },
    close: () => {
      const { peek: _peek, ...query } = route.query
      void router.push({ query })
    },
    openPage: (id: string) => void router.push({ name: 'issue', params: { id } }),
  }
}
