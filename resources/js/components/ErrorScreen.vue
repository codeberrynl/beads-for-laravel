<script setup lang="ts">
import { RefreshCw } from '@lucide/vue'
import { t } from '@/lib/i18n'
import { Button } from '@/components/ui/button'

defineProps<{ message: string; checking?: boolean }>()
const emit = defineEmits<{ retry: [] }>()
</script>

<template>
  <div class="grid h-full place-items-center p-6">
    <div class="max-w-md text-center">
      <h1 class="text-base font-semibold">{{ t('health.error') }}</h1>
      <p class="mt-2 text-[13px] text-muted-foreground">{{ message || t('health.errorBody') }}</p>
      <Button class="mt-5" size="sm" variant="outline" :disabled="checking" @click="emit('retry')">
        <RefreshCw class="size-3.5" :class="{ 'animate-spin': checking }" />
        {{ t('app.retry') }}
      </Button>
    </div>
  </div>
</template>
