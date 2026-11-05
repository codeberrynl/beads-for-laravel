<script setup lang="ts">
import IssueDetail from '@/components/detail/IssueDetail.vue'

defineProps<{ id: string }>()
</script>

<template>
  <IssueDetail :id="id" :key="id" mode="page" />
</template>
