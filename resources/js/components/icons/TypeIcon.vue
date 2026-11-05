<script setup lang="ts">
import { computed, type Component } from 'vue'
import { Activity, Atom, BookOpen, Boxes, Bug, CircleDashed, DoorClosed, Flag, FlaskConical, Mail, Scale, Sparkles, SquareCheck, Wrench } from '@lucide/vue'

const props = withDefaults(defineProps<{ type: string; size?: number }>(), { size: 14 })

const icons: Record<string, { icon: Component; color: string }> = {
  task: { icon: SquareCheck, color: 'var(--muted-foreground)' },
  bug: { icon: Bug, color: 'var(--status-blocked)' },
  feature: { icon: Sparkles, color: 'var(--agent)' },
  chore: { icon: Wrench, color: 'var(--muted-foreground)' },
  epic: { icon: Boxes, color: 'var(--primary)' },
  decision: { icon: Scale, color: 'var(--muted-foreground)' },
  spike: { icon: FlaskConical, color: 'var(--status-wip)' },
  story: { icon: BookOpen, color: 'var(--status-review)' },
  milestone: { icon: Flag, color: 'var(--primary)' },
  message: { icon: Mail, color: 'var(--subtle)' },
  molecule: { icon: Atom, color: 'var(--subtle)' },
  gate: { icon: DoorClosed, color: 'var(--subtle)' },
  event: { icon: Activity, color: 'var(--subtle)' },
}

const entry = computed(() => icons[props.type] ?? { icon: CircleDashed, color: 'var(--muted-foreground)' })
</script>

<template>
  <component :is="entry.icon" :size="size" :stroke-width="1.75" :style="{ color: entry.color }" class="shrink-0" aria-hidden="true" />
</template>
