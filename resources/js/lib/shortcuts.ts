/**
 * The central shortcut registry. Later registrations win (a peek's Escape
 * beats the page's). Nothing fires while typing in an input, textarea,
 * select or contenteditable, or while a dialog or menu is open (unless a
 * binding asks for it).
 */
export interface ShortcutOptions {
  /** Also fire while a dialog/menu/popover is open. */
  inOverlay?: boolean
}

interface Binding extends ShortcutOptions {
  combo: string
  handler: (event: KeyboardEvent) => void | false
}

const bindings: Binding[] = []

export function normaliseCombo(combo: string): string {
  return combo
    .toLowerCase()
    .split('+')
    .map((part) => (part === 'cmd' || part === 'meta' || part === 'ctrl' ? 'mod' : part === 'esc' ? 'escape' : part))
    .sort((a, b) => (a === 'mod' ? -1 : b === 'mod' ? 1 : a === 'shift' ? -1 : b === 'shift' ? 1 : 0))
    .join('+')
}

export function comboOf(event: KeyboardEvent): string {
  const key = event.key === ' ' ? 'space' : event.key.toLowerCase()
  const parts: string[] = []

  if (event.metaKey || event.ctrlKey) parts.push('mod')
  if (event.shiftKey && key.length > 1) parts.push('shift')
  parts.push(key)

  return parts.join('+')
}

export function isEditable(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) {
    return false
  }

  return target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || target.closest('[contenteditable="true"]') !== null
}

export function overlayOpen(): boolean {
  return document.querySelector('[role="dialog"][data-state="open"], [role="alertdialog"][data-state="open"], [role="menu"][data-state="open"], [data-slot="popover-content"][data-state="open"]') !== null
}

export function register(combo: string, handler: Binding['handler'], options: ShortcutOptions = {}): () => void {
  const binding: Binding = { combo: normaliseCombo(combo), handler, ...options }
  bindings.push(binding)

  return () => {
    const index = bindings.indexOf(binding)
    if (index !== -1) bindings.splice(index, 1)
  }
}

function onKeydown(event: KeyboardEvent): void {
  if (event.defaultPrevented || event.isComposing || isEditable(event.target)) {
    return
  }

  const combo = comboOf(event)
  const overlay = overlayOpen()

  for (let index = bindings.length - 1; index >= 0; index--) {
    const binding = bindings[index]

    if (!binding || binding.combo !== combo || (overlay && !binding.inOverlay)) {
      continue
    }

    if (binding.handler(event) !== false) {
      event.preventDefault()
      return
    }
  }
}

window.addEventListener('keydown', onKeydown)
