<script setup lang="ts">
import type { ApiErrorInfo } from '../composables/useApiError'

defineProps<{
  error: ApiErrorInfo | null
}>()
</script>

<template>
  <div
    v-if="error"
    :class="[
      'rounded-2xl border px-4 py-3 text-sm',
      error.isForbidden
        ? 'border-amber-500/30 bg-amber-500/10 text-amber-300'
        : 'border-red-500/20 bg-red-500/10 text-red-300'
    ]"
  >
    <div class="font-medium">
      {{ error.isForbidden ? '🔒 Acesso negado' : '⚠️ Erro' }}
    </div>
    <div class="mt-1 text-xs opacity-90">
      {{ error.message }}
    </div>
  </div>
</template>
