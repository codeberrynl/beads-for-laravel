import { t } from '@/lib/i18n'

export const PRIORITIES = [0, 1, 2, 3, 4] as const

export type Priority = (typeof PRIORITIES)[number]

export function priorityLabel(priority: number): string {
  return t(`priority.${Math.min(4, Math.max(0, priority))}`)
}

/** "High (P1)" */
export function priorityTooltip(priority: number): string {
  return t('priority.tooltip', { label: priorityLabel(priority), value: priority })
}
