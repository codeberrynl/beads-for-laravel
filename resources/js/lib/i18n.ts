import en from '@/lang/en'

type Messages = { readonly [key: string]: string | Messages }

const messages: Messages = en as unknown as Messages

function lookup(key: string): string | undefined {
  let node: string | Messages | undefined = messages

  for (const part of key.split('.')) {
    if (node === undefined || typeof node === 'string') {
      return undefined
    }
    node = node[part]
  }

  return typeof node === 'string' ? node : undefined
}

/**
 * Translate a key from lang/en.ts. "{name}" placeholders are replaced;
 * "one|many" strings pick a form by params.count.
 */
export function t(key: string, params: Record<string, string | number> = {}): string {
  let text = lookup(key) ?? key

  if (text.includes('|') && typeof params.count === 'number') {
    const [one, many] = text.split('|')
    text = (params.count === 1 ? one : many) ?? text
  }

  return text.replace(/\{(\w+)\}/g, (match, name: string) => (name in params ? String(params[name]) : match))
}

/** Translate when the key exists, otherwise return the fallback. */
export function tOr(key: string, fallback: string, params: Record<string, string | number> = {}): string {
  return lookup(key) !== undefined ? t(key, params) : fallback
}
