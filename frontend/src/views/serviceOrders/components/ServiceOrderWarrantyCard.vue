<script setup lang="ts">
import { RouterLink } from 'vue-router'
import type { ServiceOrder } from '../../../api/serviceOrders'
import { useTenantRouting } from '../../../composables/useTenantRouting'

const { tenantRoute } = useTenantRouting()

defineProps<{
  serviceOrder: ServiceOrder
}>()
</script>

<template>
  <div class="rounded-2xl border border-violet-500/20 bg-violet-500/5 p-6">
    <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-violet-400">Garantia</h2>
    <dl class="space-y-2.5">
      <div class="flex justify-between">
        <dt class="text-sm text-stone-500">OS de Origem</dt>
        <dd>
          <RouterLink
            :to="tenantRoute('service-orders.detail', { params: { id: serviceOrder.warranty_origin_service_order_id } })"
            class="text-sm text-amber-400 hover:text-amber-300"
          >
            Ver OS original
          </RouterLink>
        </dd>
      </div>
      <div class="flex justify-between">
        <dt class="text-sm text-stone-500">Sinal de Elegibilidade</dt>
        <dd>
          <span
            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
            :class="{
              'bg-emerald-500/20 text-emerald-300': serviceOrder.warranty_signal_status === 'eligible',
              'bg-red-500/20 text-red-400': serviceOrder.warranty_signal_status === 'ineligible',
              'bg-stone-500/20 text-stone-400': serviceOrder.warranty_signal_status === 'unknown' || !serviceOrder.warranty_signal_status,
            }"
          >
            {{ serviceOrder.warranty_signal_status === 'eligible' ? 'Elegível' : serviceOrder.warranty_signal_status === 'ineligible' ? 'Inelegível' : 'Desconhecido' }}
          </span>
        </dd>
      </div>
      <div class="flex justify-between">
        <dt class="text-sm text-stone-500">Decisão de Cobertura</dt>
        <dd>
                <span
                  class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="{
              'bg-amber-500/20 text-amber-300': serviceOrder.warranty_decision_status === 'pending_inspection',
              'bg-emerald-500/20 text-emerald-300': serviceOrder.warranty_decision_status === 'covered',
              'bg-red-500/20 text-red-400': serviceOrder.warranty_decision_status === 'not_covered',
                  }"
                >
            {{ serviceOrder.warranty_decision_status === 'pending_inspection' ? 'Pendente' : serviceOrder.warranty_decision_status === 'covered' ? 'Coberto' : serviceOrder.warranty_decision_status === 'not_covered' ? 'Não Coberto' : '—' }}
                </span>
        </dd>
      </div>
    </dl>
  </div>
</template>
