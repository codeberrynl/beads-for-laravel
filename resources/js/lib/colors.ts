/** A stable hue for a string (labels, avatars). */
export function hueOf(value: string): number {
  let hash = 0

  for (let index = 0; index < value.length; index++) {
    hash = (hash * 31 + value.charCodeAt(index)) | 0
  }

  return Math.abs(hash) % 360
}

export function labelColor(label: string): string {
  return `oklch(0.68 0.14 ${hueOf(label)})`
}

export function initials(name: string): string {
  return (
    name
      .split(/[\s._-]+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase())
      .join('') || '?'
  )
}
