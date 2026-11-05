export interface BeadsConfig {
  basePath: string
  apiPath: string
  csrfToken: string
  pollInterval: number
  assets: { hot: boolean; published: boolean; stale: boolean; command: string }
}

declare global {
  interface Window {
    Beads?: Partial<BeadsConfig>
  }
}

const raw = window.Beads ?? {}

export const config: BeadsConfig = {
  basePath: raw.basePath ?? '/beads',
  apiPath: raw.apiPath ?? '/beads/api',
  csrfToken: raw.csrfToken ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
  pollInterval: raw.pollInterval ?? 3,
  assets: raw.assets ?? { hot: false, published: true, stale: false, command: 'php artisan beads:publish' },
}
