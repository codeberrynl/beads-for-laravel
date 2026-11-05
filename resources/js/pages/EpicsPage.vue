<script setup lang="ts">
import ViewHeader from '@/components/ViewHeader.vue'
import { t } from '@/lib/i18n'
</script>

<template>
  <div class="flex h-full flex-col">
    <ViewHeader :title="t('views.epics')" />
  </div>
</template>
