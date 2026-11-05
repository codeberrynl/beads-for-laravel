import type { IssueRow, StatusDefinition } from '@/lib/types'
import { categoryOf, isDone } from '@/lib/status'

export type ListView = 'all' | 'active' | 'backlog' | 'mine' | 'ready' | 'blocked'

export interface ViewContext {
  actor: string
  statuses: StatusDefinition[]
}

export function isBlocked(row: IssueRow, statuses: StatusDefinition[]): boolean {
  return !isDone(row.status, statuses) && (row.status === 'blocked' || row.blocked_by.length > 0)
}

export function isMine(row: IssueRow, actor: string): boolean {
  return row.assignee !== null && row.assignee.toLowerCase() === actor.toLowerCase()
}

/** Whether a row belongs to one of the fixed list views. */
export function inView(row: IssueRow, view: ListView, context: ViewContext): boolean {
  const category = categoryOf(row.status, context.statuses)

  switch (view) {
    case 'active':
      return category === 'active' || category === 'wip'
    case 'backlog':
      return category === 'frozen'
    case 'mine':
      return isMine(row, context.actor)
    case 'ready':
      return row.ready
    case 'blocked':
      return isBlocked(row, context.statuses)
    default:
      return true
  }
}

/** Sidebar counts: issues that are not done, per view. */
export function viewCounts(rows: IssueRow[], context: ViewContext): Record<ListView | 'epics', number> {
  const open = rows.filter((row) => !isDone(row.status, context.statuses))

  return {
    all: open.length,
    active: open.filter((row) => inView(row, 'active', context)).length,
    backlog: open.filter((row) => inView(row, 'backlog', context)).length,
    mine: open.filter((row) => inView(row, 'mine', context)).length,
    ready: open.filter((row) => row.ready).length,
    blocked: open.filter((row) => isBlocked(row, context.statuses)).length,
    epics: open.filter((row) => row.type === 'epic').length,
  }
}
