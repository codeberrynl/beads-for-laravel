import type { StatusCategory, StatusDefinition } from '@/lib/types'
import { tOr } from '@/lib/i18n'

/** Categories of the built-in statuses, for when definitions are missing. */
export const BUILT_IN_CATEGORIES: Record<string, StatusCategory> = {
  open: 'active',
  in_progress: 'wip',
  blocked: 'wip',
  deferred: 'frozen',
  closed: 'done',
  pinned: 'frozen',
  hooked: 'wip',
}

export type StatusTone = StatusCategory | 'blocked' | 'none'

export function categoryOf(status: string, definitions: StatusDefinition[] = []): StatusCategory | null {
  return definitions.find((definition) => definition.name === status)?.category ?? BUILT_IN_CATEGORIES[status] ?? null
}

/** The visual tone: the category, but "blocked" stands on its own. */
export function toneOf(status: string, definitions: StatusDefinition[] = []): StatusTone {
  if (status === 'blocked') {
    return 'blocked'
  }

  return categoryOf(status, definitions) ?? 'none'
}

export function isDone(status: string, definitions: StatusDefinition[] = []): boolean {
  return categoryOf(status, definitions) === 'done'
}

/** "in_review" → "In Review"; built-in names come from lang/en.ts. */
export function statusLabel(status: string): string {
  const fallback = status
    .split(/[_\s-]+/)
    .filter(Boolean)
    .map((word) => word[0]?.toUpperCase() + word.slice(1))
    .join(' ')

  return tOr(`status.names.${status}`, fallback || status)
}

/** UI order: frozen → active → wip → blocked → done. */
export function rankOf(status: string, definitions: StatusDefinition[] = []): number {
  const definition = definitions.find((item) => item.name === status)

  if (definition) {
    return definition.rank
  }

  if (status === 'blocked') {
    return 4
  }

  return { frozen: 0, active: 1, wip: 2, done: 5 }[categoryOf(status) ?? 'wip'] ?? 3
}
