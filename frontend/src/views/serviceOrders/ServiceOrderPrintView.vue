<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { ApiError } from '../../api/client'
import {
  getPrintableServiceOrder,
  type PrintableServiceOrderPayload,
  type ServiceOrderStatus,
} from '../../api/serviceOrders'

const route = useRoute()

const payload = ref<PrintableServiceOrderPayload | null>(null)
const loading = ref(true)
const error = ref('')

const statusLabels: Record<ServiceOrderStatus, string> = {
  draft: 'Rascunho',
  open: 'Aberta',
  in_progress: 'Em Execucao',
  completed: 'Concluida',
  ready_for_pickup: 'Pronta para retirada',
  delivered: 'Entregue',
  cancelled: 'Cancelada',
  no_repair: 'Sem reparo',
}

const orderDisplayNumber = computed(() => {
  const order = payload.value?.service_order
  return order?.reference ?? (order?.order_number ? `OS-${String(order.order_number).padStart(6, '0')}` : 'OS')
})

function printDocument() {
  window.print()
}

function fmt(dt: string | null) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(async () => {
  try {
    const response = await getPrintableServiceOrder(Number(route.params.id))
    payload.value = response.data
  } catch (err) {
    if (err instanceof ApiError) {
      error.value = (err.data as { message?: string }).message ?? 'Nao foi possivel carregar a versao imprimivel.'
    } else {
      error.value = 'Nao foi possivel carregar a versao imprimivel.'
    }
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-stone-100 text-stone-900">
    <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-4 print:hidden sm:px-6">
      <RouterLink :to="{ name: 'service-orders.detail', params: { id: route.params.id } }" class="text-sm text-stone-600 hover:text-stone-900">
        ← Voltar para a O.S.
      </RouterLink>
      <button
        type="button"
        class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-stone-700"
        @click="printDocument"
      >
        Imprimir agora
      </button>
    </div>

    <div v-if="loading" class="py-20 text-center text-stone-500">Carregando versao imprimivel...</div>

    <div v-else-if="error" class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
      <div class="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ error }}
      </div>
    </div>

    <div v-else-if="payload" class="mx-auto max-w-5xl space-y-6 px-4 pb-10 sm:px-6">
      <section
        v-for="copy in payload.copies"
        :key="copy.key"
        class="print-sheet bg-white shadow-sm ring-1 ring-stone-200"
      >
        <header class="border-b border-stone-200 px-8 py-6">
          <div class="flex items-start justify-between gap-6">
            <div class="space-y-1">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-stone-500">{{ copy.label }}</p>
              <h1 class="text-2xl font-semibold text-stone-950">{{ payload.company.name || 'Empresa sem nome' }}</h1>
              <div class="space-y-0.5 text-sm text-stone-600">
                <p v-if="payload.company.document">Documento: {{ payload.company.document }}</p>
                <p v-if="payload.company.email">E-mail: {{ payload.company.email }}</p>
                <p v-if="payload.company.phone">Telefone: {{ payload.company.phone }}</p>
              </div>
            </div>
            <div class="space-y-2 text-right">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-stone-500">Ordem de Servico</p>
              <p class="text-2xl font-semibold text-stone-950">{{ orderDisplayNumber }}</p>
              <p class="text-sm text-stone-600">Status: {{ statusLabels[payload.service_order.status] ?? payload.service_order.status }}</p>
              <p class="text-sm text-stone-600">Abertura: {{ fmt(payload.service_order.opened_at) }}</p>
            </div>
          </div>
        </header>

        <div class="grid gap-6 px-8 py-6 md:grid-cols-2">
          <section class="space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-[0.24em] text-stone-500">Cliente</h2>
            <div class="rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-700">
              <p><span class="font-semibold text-stone-950">Nome:</span> {{ payload.service_order.customer.name || '—' }}</p>
              <p><span class="font-semibold text-stone-950">Documento:</span> {{ payload.service_order.customer.document || '—' }}</p>
              <p><span class="font-semibold text-stone-950">Contato:</span> {{ payload.service_order.customer.contact_name || '—' }}</p>
              <p><span class="font-semibold text-stone-950">Telefone:</span> {{ payload.service_order.customer.contact_phone || '—' }}</p>
            </div>
          </section>

          <section class="space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-[0.24em] text-stone-500">Equipamento</h2>
            <div class="rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-700">
              <p><span class="font-semibold text-stone-950">Categoria:</span> {{ payload.service_order.equipment.category || '—' }}</p>
              <p><span class="font-semibold text-stone-950">Marca:</span> {{ payload.service_order.equipment.brand || '—' }}</p>
              <p><span class="font-semibold text-stone-950">Modelo:</span> {{ payload.service_order.equipment.model || '—' }}</p>
              <p><span class="font-semibold text-stone-950">Serial:</span> {{ payload.service_order.equipment.serial_number || '—' }}</p>
              <p><span class="font-semibold text-stone-950">Descricao registrada:</span> {{ payload.service_order.equipment_description || '—' }}</p>
            </div>
          </section>
        </div>

        <div class="grid gap-6 px-8 pb-6 md:grid-cols-[1.1fr_0.9fr]">
          <section class="space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-[0.24em] text-stone-500">Defeito relatado</h2>
            <div class="min-h-28 rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm leading-6 text-stone-700">
              {{ payload.service_order.reported_problem || '—' }}
            </div>
          </section>

          <section class="space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-[0.24em] text-stone-500">Observacoes</h2>
            <div class="min-h-28 rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm leading-6 text-stone-700">
              <ul v-if="payload.service_order.observations.length" class="list-disc space-y-1 pl-5">
                <li v-for="note in payload.service_order.observations" :key="note">{{ note }}</li>
              </ul>
              <p v-else>—</p>
            </div>
          </section>
        </div>

        <section class="px-8 pb-6">
          <h2 class="mb-3 text-xs font-semibold uppercase tracking-[0.24em] text-stone-500">Checklist de entrada</h2>
          <div class="overflow-hidden rounded-xl border border-stone-200">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
              <thead class="bg-stone-50 text-left text-stone-500">
                <tr>
                  <th class="px-4 py-3 font-semibold">Item</th>
                  <th class="px-4 py-3 font-semibold">Status</th>
                  <th class="px-4 py-3 font-semibold">Observacao</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-stone-200 bg-white text-stone-700">
                <tr v-for="item in payload.service_order.intake_checklist" :key="item.label">
                  <td class="px-4 py-3">{{ item.label }}</td>
                  <td class="px-4 py-3">{{ item.checked ? 'Conferido' : 'Nao conferido' }}</td>
                  <td class="px-4 py-3">{{ item.notes || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <footer class="grid gap-8 border-t border-stone-200 px-8 py-8 md:grid-cols-2">
          <div
            v-for="signature in payload.service_order.signature_blocks"
            :key="`${copy.key}-${signature.key}`"
            class="space-y-3"
          >
            <div class="h-16 border-b border-stone-400"></div>
            <p class="text-sm font-medium text-stone-700">{{ signature.label }}</p>
          </div>
        </footer>
      </section>
    </div>
  </div>
</template>

<style scoped>
.print-sheet {
  page-break-after: always;
}

.print-sheet:last-child {
  page-break-after: auto;
}

@media print {
  .print-sheet {
    margin: 0;
    min-height: 100vh;
    box-shadow: none;
    break-after: page;
  }
}
</style>
