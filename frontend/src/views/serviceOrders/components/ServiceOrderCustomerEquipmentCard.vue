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
  <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
    <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Cliente &amp; Equipamento</h2>
    <dl class="space-y-2.5">
      <div class="flex justify-between">
        <dt class="text-sm text-stone-500">Cliente</dt>
        <dd>
          <RouterLink
            v-if="serviceOrder.customer_id"
            :to="tenantRoute('customers.detail', { params: { id: serviceOrder.customer_id } })"
            class="text-sm text-amber-400 hover:text-amber-300"
          >
            {{ serviceOrder.customer_name }}
          </RouterLink>
          <span v-else class="text-sm text-stone-500">—</span>
        </dd>
      </div>
      <div class="flex justify-between">
        <dt class="text-sm text-stone-500">Contato</dt>
        <dd class="text-sm text-stone-200">{{ serviceOrder.customer_contact_name ?? '—' }}</dd>
      </div>
      <div class="flex justify-between">
        <dt class="text-sm text-stone-500">Telefone</dt>
        <dd class="text-sm text-stone-200">{{ serviceOrder.customer_contact_phone ?? '—' }}</dd>
      </div>
      <div class="border-t border-white/5 pt-2.5">
        <div class="flex justify-between">
          <dt class="text-sm text-stone-500">Equipamento</dt>
          <dd>
            <RouterLink
              v-if="serviceOrder.equipment_id"
              :to="tenantRoute('equipments.detail', { params: { id: serviceOrder.equipment_id } })"
              class="text-sm text-amber-400 hover:text-amber-300"
            >
              {{ [serviceOrder.equipment_brand, serviceOrder.equipment_model].filter(Boolean).join(' ') }}
            </RouterLink>
            <span v-else class="text-sm text-stone-500">—</span>
          </dd>
        </div>
        <div class="mt-1.5 flex justify-between">
          <dt class="text-sm text-stone-500">Nº de Série</dt>
          <dd class="font-mono text-xs text-stone-400">{{ serviceOrder.equipment_serial_number ?? '—' }}</dd>
        </div>
      </div>
    </dl>
  </div>
</template>
