import { onMounted, onUnmounted } from 'vue'
import { register, type ShortcutOptions } from '@/lib/shortcuts'

/** Register a shortcut for the lifetime of the component. */
export function useShortcut(combo: string | string[], handler: (event: KeyboardEvent) => void | false, options: ShortcutOptions = {}): void {
  let unregister: Array<() => void> = []

  onMounted(() => {
    unregister = (Array.isArray(combo) ? combo : [combo]).map((item) => register(item, handler, options))
  })

  onUnmounted(() => unregister.forEach((off) => off()))
}
