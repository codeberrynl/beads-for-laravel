import { config } from '@/lib/config'
import type { ApiErrorBody, Bootstrap, IssueDetailPayload, SnapshotPayload, TextField } from '@/lib/types'

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly body: ApiErrorBody = {},
  ) {
    super(message)
  }

  get isConflict(): boolean {
    return this.status === 409 && this.code === 'conflict'
  }

  get isUnhealthy(): boolean {
    return this.status === 503 && this.body.health !== undefined
  }
}

interface Response<T> {
  status: number
  data: T | null
  etag: string | null
}

const MAX_BUSY_RETRIES = 4

const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms))

async function request<T>(method: string, path: string, body?: unknown, etag?: string | null): Promise<Response<T>> {
  for (let attempt = 0; ; attempt++) {
    let response: globalThis.Response

    try {
      response = await fetch(`${config.apiPath}/${path}`, {
        method,
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': config.csrfToken,
          ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
          ...(etag ? { 'If-None-Match': etag } : {}),
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
      })
    } catch {
      throw new ApiError(0, 'network', 'Could not reach the server.')
    }

    if (response.status === 304) {
      return { status: 304, data: null, etag: response.headers.get('ETag') }
    }

    const payload = (await response.json().catch(() => ({}))) as T & ApiErrorBody

    if (response.ok) {
      return { status: response.status, data: payload, etag: response.headers.get('ETag') }
    }

    const code = payload.error?.code ?? (response.status === 422 && payload.errors ? 'validation' : 'error')

    // The workspace lock is held by another bd call: wait and try again.
    if (response.status === 503 && code === 'workspace_busy' && attempt < MAX_BUSY_RETRIES) {
      await sleep(Number(response.headers.get('Retry-After') ?? 1) * 1000)
      continue
    }

    const message = payload.error?.message ?? (payload.errors ? Object.values(payload.errors).flat()[0] : undefined) ?? payload.message ?? `Request failed (${response.status})`

    throw new ApiError(response.status, code, message, payload)
  }
}

async function data<T>(method: string, path: string, body?: unknown): Promise<T> {
  return (await request<T>(method, path, body)).data as T
}

export type UpdateFields = Partial<Record<TextField, string | null>> & {
  status?: string
  priority?: number
  type?: string
  assignee?: string | null
  parent?: string | null
  labels?: { add?: string[]; remove?: string[] }
  base?: Partial<Record<TextField, string>>
}

export interface CreateFields {
  title: string
  type?: string
  priority?: number
  status?: string
  description?: string
  assignee?: string | null
  labels?: string[]
  parent?: string | null
}

export type RelationType = 'blocks' | 'related' | 'parent-child' | string

export interface RelationInput {
  type: RelationType
  target: string
  direction?: 'blocked_by' | 'blocking' | 'parent' | 'child' | 'both' | 'incoming' | 'outgoing'
}

const issuePath = (id: string) => `issues/${encodeURIComponent(id)}`

export const api = {
  bootstrap: () => data<Bootstrap>('GET', 'bootstrap'),
  snapshot: (all: boolean, etag: string | null) => request<SnapshotPayload>('GET', all ? 'snapshot?closed=all' : 'snapshot', undefined, etag),
  issue: (id: string) => data<IssueDetailPayload>('GET', issuePath(id)),
  create: (fields: CreateFields) => data<IssueDetailPayload>('POST', 'issues', fields),
  update: (id: string, fields: UpdateFields) => data<IssueDetailPayload>('PATCH', issuePath(id), fields),
  close: (id: string, reason?: string, force = false) => data<IssueDetailPayload>('POST', `${issuePath(id)}/close`, { reason: reason ?? null, force }),
  reopen: (id: string) => data<IssueDetailPayload>('POST', `${issuePath(id)}/reopen`, {}),
  addRelation: (id: string, relation: RelationInput) => data<IssueDetailPayload>('POST', `${issuePath(id)}/relations`, relation),
  removeRelation: (id: string, relation: RelationInput) => data<IssueDetailPayload>('DELETE', `${issuePath(id)}/relations`, relation),
  comment: (id: string, text: string) => data<IssueDetailPayload>('POST', `${issuePath(id)}/comments`, { text }),
}
