<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ViewHeader from '@/components/ViewHeader.vue'
import { t } from '@/lib/i18n'

const route = useRoute()
const titles = { all: 'views.all', active: 'views.active', backlog: 'views.backlog', mine: 'views.myIssues', ready: 'views.ready', blocked: 'views.blocked' } as const
const title = computed(() => t(titles[route.meta.view ?? 'all']))
</script>

<template>
  <div class="flex h-full flex-col">
    <ViewHeader :title="title" />
  </div>
</template>
