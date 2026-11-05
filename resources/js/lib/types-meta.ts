import { tOr } from '@/lib/i18n'

export const SYSTEM_TYPES = ['message', 'molecule', 'gate', 'event']

export function typeLabel(type: string): string {
  return tOr(`type.names.${type}`, type.charAt(0).toUpperCase() + type.slice(1))
}
