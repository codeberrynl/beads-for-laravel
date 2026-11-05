import { Editor, type AnyExtension } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import { TaskItem, TaskList } from '@tiptap/extension-list'
import { Markdown } from '@tiptap/markdown'

/** The extensions of every markdown editor and renderer. */
export function markdownExtensions(extra: AnyExtension[] = []): AnyExtension[] {
  return [
    StarterKit.configure({
      link: { openOnClick: false, autolink: true, HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: '_blank' } },
      heading: { levels: [1, 2, 3, 4] },
    }),
    TaskList,
    TaskItem.configure({ nested: true }),
    Markdown,
    ...extra,
  ]
}

let headless: Editor | null = null

/** Markdown → editor → markdown, with the same schema as the editors. */
export function roundTrip(markdown: string): string {
  headless ??= new Editor({ element: null, extensions: markdownExtensions(), content: '', editable: false })
  headless.commands.setContent(markdown, { contentType: 'markdown', emitUpdate: false })

  return headless.getMarkdown()
}

/** Code (fenced, indented, inline) cannot hold lossy constructs. */
function withoutCode(markdown: string): string {
  return markdown
    .replace(/^(```|~~~)[^\n]*\n[\s\S]*?^\1[ \t]*$/gm, '')
    .replace(/`[^`\n]*`/g, '')
}

/** Letters and digits only: what must survive a round trip. */
function signature(markdown: string): string {
  return markdown.replace(/[^\p{L}\p{N}]+/gu, '')
}

export type LossReason = 'html' | 'footnotes' | 'tables' | 'content'

/**
 * Why this markdown cannot be edited losslessly in the rich editor (empty
 * when it can): raw HTML, footnotes and tables are not in the schema, and
 * any other text that a round trip drops counts as "content".
 */
export function lossReasons(markdown: string): LossReason[] {
  if (markdown.trim() === '') {
    return []
  }

  const prose = withoutCode(markdown)
  const reasons: LossReason[] = []

  if (/<\/?[a-z][\w-]*(?:\s[^<>]*)?\/?>|<!--/i.test(prose)) {
    reasons.push('html')
  }

  if (/\[\^[^\]\s]+\]/.test(prose)) {
    reasons.push('footnotes')
  }

  if (/^[ \t]*\|?[^\n|]*\|[^\n]*\n[ \t]*\|?[ \t]*:?-{2,}:?[ \t]*(?:\|[ \t]*:?-{2,}:?[ \t]*)*\|?[ \t]*$/m.test(prose)) {
    reasons.push('tables')
  }

  if (reasons.length === 0 && signature(roundTrip(markdown)) !== signature(markdown)) {
    reasons.push('content')
  }

  return reasons
}

export function isLossless(markdown: string): boolean {
  return lossReasons(markdown).length === 0
}
