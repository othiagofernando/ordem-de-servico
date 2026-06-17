<script setup lang="ts">
import type { ServiceOrder } from '../../../api/serviceOrders'

defineProps<{
  serviceOrder: ServiceOrder
  formatDateTime: (value: string | null) => string
}>()
</script>

<template>
  <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
    <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Diagnóstico</h2>
    <div v-if="serviceOrder.diagnosis_completed_at" class="space-y-3">
      <div>
        <p class="mb-1 text-xs text-stone-500">Notas Internas</p>
        <p class="text-sm text-stone-300">{{ serviceOrder.diagnosis_internal_notes }}</p>
      </div>
      <div>
        <p class="mb-1 text-xs text-stone-500">Resumo para o Cliente</p>
        <p class="text-sm text-stone-300">{{ serviceOrder.diagnosis_customer_summary }}</p>
      </div>
      <p class="text-xs text-stone-600">Concluído em {{ formatDateTime(serviceOrder.diagnosis_completed_at) }}</p>
    </div>
    <p v-else class="text-sm text-stone-500">Diagnóstico ainda não registrado.</p>
    <div class="mt-3 border-t border-white/5 pt-3">
      <p class="text-xs text-stone-500">Problema relatado</p>
      <p class="mt-1 text-sm text-stone-300">{{ serviceOrder.reported_problem ?? '—' }}</p>
    </div>
  </div>
</template>
