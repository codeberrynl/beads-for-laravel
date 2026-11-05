import type { LocationQuery, LocationQueryRaw } from 'vue-router'
import type { IssueRow, StatusDefinition, TypeDefinition } from '@/lib/types'
import { rankOf, statusLabel, toneOf, type StatusTone } from '@/lib/status'
import { priorityLabel } from '@/lib/priority'
import { typeLabel } from '@/lib/types-meta'
import { t } from '@/lib/i18n'

export type GroupBy = 'status' | 'priority' | 'type' | 'assignee' | 'none'
export type SortBy = 'priority' | 'updated' | 'created' | 'title'

export const GROUPS: GroupBy[] = ['status', 'priority', 'type', 'assignee', 'none']
export const SORTS: SortBy[] = ['priority', 'updated', 'created', 'title']

/** "" in assignee means unassigned; "none" in parent means no parent. */
export interface ListFilters {
  status: string[]
  priority: number[]
  type: string[]
  label: string[]
  assignee: string[]
  parent: string[]
  q: string
}

export interface DisplayOptions {
  group: GroupBy
  sort: SortBy
}

export const UNASSIGNED = ''
export const NO_PARENT = 'none'

export function emptyFilters(): ListFilters {
  return { status: [], priority: [], type: [], label: [], assignee: [], parent: [], q: '' }
}

export function hasFilters(filters: ListFilters): boolean {
  return (
    filters.q.trim() !== '' ||
    [filters.status, filters.priority, filters.type, filters.label, filters.assignee, filters.parent].some((values) => values.length > 0)
  )
}

/** Comma-separated values; "assignee=" (empty) means [""], i.e. unassigned. */
function values(query: LocationQuery, key: string): string[] {
  const value = query[key]

  if (value === undefined || value === null) {
    return []
  }

  const raw = Array.isArray(value) ? value.filter((item) => item !== null).join(',') : value

  return raw.split(',')
}

/** Filters and display options live in the URL query, so they survive a reload and can be shared. */
export function parseQuery(query: LocationQuery): { filters: ListFilters; display: DisplayOptions } {
  const group = String(query.group ?? '')
  const sort = String(query.sort ?? '')

  return {
    filters: {
      status: values(query, 'status'),
      priority: values(query, 'priority')
        .map(Number)
        .filter((value) => Number.isInteger(value) && value >= 0 && value <= 4),
      type: values(query, 'type'),
      label: values(query, 'label'),
      assignee: values(query, 'assignee'),
      parent: values(query, 'parent'),
      q: typeof query.q === 'string' ? query.q : '',
    },
    display: {
      group: (GROUPS as string[]).includes(group) ? (group as GroupBy) : 'status',
      sort: (SORTS as string[]).includes(sort) ? (sort as SortBy) : 'priority',
    },
  }
}

export function toQuery(filters: ListFilters, display: DisplayOptions, rest: LocationQuery = {}): LocationQueryRaw {
  const query: LocationQueryRaw = {}

  for (const [key, value] of Object.entries(rest)) {
    if (!['status', 'priority', 'type', 'label', 'assignee', 'parent', 'q', 'group', 'sort'].includes(key)) {
      query[key] = value
    }
  }

  for (const key of ['status', 'priority', 'type', 'label', 'assignee', 'parent'] as const) {
    if (filters[key].length > 0) {
      query[key] = filters[key].map(String).join(',')
    }
  }

  if (filters.q.trim() !== '') {
    query.q = filters.q
  }

  if (display.group !== 'status') {
    query.group = display.group
  }

  if (display.sort !== 'priority') {
    query.sort = display.sort
  }

  return query
}

/** "p0 checkout" → priorities [0] and the text "checkout". */
export function parseSearch(q: string): { text: string; priorities: number[] } {
  const priorities: number[] = []
  const words: string[] = []

  for (const word of q.trim().split(/\s+/).filter(Boolean)) {
    const match = /^p([0-4])$/i.exec(word)

    if (match) {
      priorities.push(Number(match[1]))
    } else {
      words.push(word)
    }
  }

  return { text: words.join(' ').toLowerCase(), priorities }
}

export function matchesSearch(row: IssueRow, q: string): boolean {
  const { text, priorities } = parseSearch(q)

  if (priorities.length > 0 && !priorities.includes(row.priority)) {
    return false
  }

  return text === '' || row.id.toLowerCase().includes(text) || row.title.toLowerCase().includes(text)
}

export function applyFilters(rows: IssueRow[], filters: ListFilters): IssueRow[] {
  return rows.filter((row) => {
    if (filters.status.length && !filters.status.includes(row.status)) return false
    if (filters.priority.length && !filters.priority.includes(row.priority)) return false
    if (filters.type.length && !filters.type.includes(row.type)) return false
    if (filters.label.length && !filters.label.some((label) => row.labels.includes(label))) return false
    if (filters.assignee.length && !filters.assignee.includes(row.assignee ?? UNASSIGNED)) return false
    if (filters.parent.length && !filters.parent.includes(row.parent ?? NO_PARENT)) return false

    return matchesSearch(row, filters.q)
  })
}

const time = (value: string | null) => (value ? Date.parse(value) : 0)

export function sortRows(rows: IssueRow[], sort: SortBy): IssueRow[] {
  const byUpdated = (a: IssueRow, b: IssueRow) => time(b.updated_at) - time(a.updated_at)

  const compare: Record<SortBy, (a: IssueRow, b: IssueRow) => number> = {
    priority: (a, b) => a.priority - b.priority || byUpdated(a, b),
    updated: byUpdated,
    created: (a, b) => time(b.created_at) - time(a.created_at) || byUpdated(a, b),
    title: (a, b) => a.title.localeCompare(b.title, 'en', { sensitivity: 'base' }) || a.id.localeCompare(b.id),
  }

  return [...rows].sort(compare[sort])
}

export interface IssueGroup {
  key: string
  label: string
  rows: IssueRow[]
  /** For status groups: the tone of the status icon. */
  tone?: StatusTone
  /** For priority groups. */
  priority?: number
  /** For type groups. */
  type?: string
  /** For assignee groups (null = unassigned). */
  assignee?: string | null
}

export function groupRows(rows: IssueRow[], group: GroupBy, statuses: StatusDefinition[], types: TypeDefinition[] = []): IssueGroup[] {
  if (group === 'none') {
    return [{ key: 'all', label: '', rows }]
  }

  const buckets = new Map<string, IssueRow[]>()

  for (const row of rows) {
    const key = group === 'status' ? row.status : group === 'priority' ? String(row.priority) : group === 'type' ? row.type : (row.assignee ?? UNASSIGNED)
    buckets.set(key, [...(buckets.get(key) ?? []), row])
  }

  const keys = [...buckets.keys()]

  if (group === 'status') {
    keys.sort((a, b) => rankOf(a, statuses) - rankOf(b, statuses) || statuses.findIndex((s) => s.name === a) - statuses.findIndex((s) => s.name === b))

    return keys.map((key) => ({ key, label: statusLabel(key), rows: buckets.get(key) ?? [], tone: toneOf(key, statuses) }))
  }

  if (group === 'priority') {
    keys.sort((a, b) => Number(a) - Number(b))

    return keys.map((key) => ({ key, label: priorityLabel(Number(key)), rows: buckets.get(key) ?? [], priority: Number(key) }))
  }

  if (group === 'type') {
    const order = types.map((type) => type.name)
    keys.sort((a, b) => (order.indexOf(a) + 1 || 99) - (order.indexOf(b) + 1 || 99) || a.localeCompare(b))

    return keys.map((key) => ({ key, label: typeLabel(key), rows: buckets.get(key) ?? [], type: key }))
  }

  keys.sort((a, b) => (a === UNASSIGNED ? 1 : b === UNASSIGNED ? -1 : a.localeCompare(b)))

  return keys.map((key) => ({ key, label: key === UNASSIGNED ? t('filters.unassigned') : key, rows: buckets.get(key) ?? [], assignee: key === UNASSIGNED ? null : key }))
}
