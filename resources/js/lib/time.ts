import { t } from '@/lib/i18n'

export function relativeTime(value: string | null | undefined, now = Date.now()): string {
  if (!value) {
    return ''
  }

  const date = new Date(value)
  const seconds = Math.max(0, Math.round((now - date.getTime()) / 1000))

  if (seconds < 60) {
    return t('time.justNow')
  }
  if (seconds < 3600) {
    return t('time.minutes', { count: Math.floor(seconds / 60) })
  }
  if (seconds < 86400) {
    return t('time.hours', { count: Math.floor(seconds / 3600) })
  }
  if (seconds < 86400 * 7) {
    return t('time.days', { count: Math.floor(seconds / 86400) })
  }

  return shortDate(value)
}

export function shortDate(value: string | null | undefined): string {
  if (!value) {
    return ''
  }

  const date = new Date(value)
  const sameYear = date.getFullYear() === new Date().getFullYear()

  return date.toLocaleDateString('en', { month: 'short', day: 'numeric', ...(sameYear ? {} : { year: 'numeric' }) })
}

export function fullDate(value: string | null | undefined): string {
  return value ? new Date(value).toLocaleString('en', { dateStyle: 'medium', timeStyle: 'short' }) : ''
}
