/** Hand-written types for the JSON API of the package. */

export type StatusCategory = 'frozen' | 'active' | 'wip' | 'done'

export interface StatusDefinition {
  name: string
  category: StatusCategory | null
  icon: string | null
  description: string | null
  built_in: boolean
  rank: number
}

export interface TypeDefinition {
  name: string
  kind: 'core' | 'system' | 'custom'
  description: string | null
}

export type HealthState = 'binary_missing' | 'version_too_old' | 'workspace_missing' | 'schema_migration_required' | 'ok'

export interface Health {
  state: HealthState
  binary: string | null
  searched: string[]
  version: string | null
  minimum_version: string
  workspace: string
}

export interface Bootstrap {
  actor: string
  humans: string[]
  statuses: StatusDefinition[]
  types: TypeDefinition[]
  hash: string | null
  bd_version: string | null
  health: Health
  poll_interval: number
  closed_window_days: number
  assets: { hot: boolean; published: boolean; stale: boolean; command: string }
}

export interface Progress {
  done: number
  total: number
}

export interface IssueRow {
  id: string
  title: string
  status: string
  priority: number
  type: string
  assignee: string | null
  created_by: string | null
  created_at: string | null
  updated_at: string | null
  closed_at: string | null
  defer_until: string | null
  due_at: string | null
  parent: string | null
  labels: string[]
  comment_count: number
  blocked_by: string[]
  ready: boolean
  progress: Progress | null
}

export interface Relation {
  from: string
  to: string
  type: string
}

export interface SnapshotPayload {
  hash: string
  exported_at: string
  closed: 'window' | 'all'
  hidden_closed: number
  issues: IssueRow[]
  relations: Relation[]
}

export interface Dependency {
  issue_id: string
  depends_on_id: string
  type: string
  created_at: string | null
  created_by: string | null
}

export interface Comment {
  id: string
  issue_id: string
  author: string
  text: string
  created_at: string | null
}

export interface Issue extends Omit<IssueRow, 'comment_count'> {
  description: string
  design: string
  acceptance_criteria: string
  notes: string
  owner: string | null
  started_at: string | null
  close_reason: string | null
  external_ref: string | null
  dependencies: Dependency[]
  comments: Comment[]
  comment_count: number
  author_is_human: boolean
}

export interface IssueRelations {
  parent: string | null
  children: string[]
  blocked_by: string[]
  blocking: string[]
  related: string[]
  other: Dependency[]
}

export interface IssueDetailPayload {
  hash: string
  issue: Issue
  relations: IssueRelations
  issues: Record<string, IssueRow>
}

export type TextField = 'title' | 'description' | 'design' | 'acceptance_criteria' | 'notes'

export interface ApiErrorBody {
  error?: { code?: string; message?: string; hint?: string }
  health?: Health
  field?: TextField
  theirs?: string
  errors?: Record<string, string[]>
  message?: string
}
