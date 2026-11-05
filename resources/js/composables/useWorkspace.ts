import { computed } from 'vue'
import { useBootstrap } from '@/composables/useBeads'
import type { StatusDefinition, TypeDefinition } from '@/lib/types'

/** Definitions and the actor from the bootstrap payload. */
export function useWorkspace() {
  const bootstrap = useBootstrap()

  const statuses = computed<StatusDefinition[]>(() => bootstrap.data.value?.statuses ?? [])
  const types = computed<TypeDefinition[]>(() => bootstrap.data.value?.types ?? [])
  const selectableTypes = computed(() => types.value.filter((type) => type.kind !== 'system'))
  const actor = computed(() => bootstrap.data.value?.actor ?? '')
  const humans = computed(() => bootstrap.data.value?.humans ?? [])
  const workspaceName = computed(() => bootstrap.data.value?.health.workspace.split('/').filter(Boolean).pop() ?? 'beads')

  function isHuman(author: string | null | undefined): boolean {
    if (!author) {
      return true
    }

    return humans.value.some((human) => human.toLowerCase() === author.toLowerCase())
  }

  return { bootstrap, statuses, types, selectableTypes, actor, humans, workspaceName, isHuman }
}
