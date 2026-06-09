<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { getOverview, type OverviewData } from '../../api/serviceOrders'

const data = ref<OverviewData | null>(null)
const loading = ref(true)
const error = ref(false)

const statusLabels: Record<string, string> = {
  draft: 'Rascunho',
  open: 'Aberta',
  in_progress: 'Em Execução',
  completed: 'Concluída',
  ready_for_pickup: 'Aguard. Retirada',
  delivered: 'Entregue',
  cancelled: 'Cancelada',
  no_repair: 'Sem Reparo',
}

const paymentLabels: Record<string, string> = {
  pix: 'PIX',
  cash: 'Dinheiro',
  credit_card: 'Cartão Crédito',
  debit_card: 'Cartão Débito',
  transfer: 'Transferência',
  check: 'Cheque',
}

function formatMoney(cents: number) {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

onMounted(async () => {
  try {
    const res = await getOverview()
    data.value = res.data
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-white">Dashboard</h1>
      <RouterLink
        :to="{ name: 'service-orders.create' }"
        class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
      >
        + Nova OS
      </RouterLink>
    </div>

    <div v-if="loading" class="text-center py-16 text-stone-500">Carregando…</div>
    <div v-else-if="error" class="rounded-xl border border-red-500/20 bg-red-500/10 p-6 text-red-300">
      Não foi possível carregar os dados.
    </div>

    <template v-else-if="data">
      <!-- Total geral -->
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Total de OS</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ data.totals.service_orders }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Recebido</p>
          <p class="mt-2 text-2xl font-bold text-emerald-400">
            {{ formatMoney(data.received_amounts.total_received_cents) }}
          </p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Em Execução</p>
          <p class="mt-2 text-3xl font-bold text-amber-400">{{ data.by_status.in_progress ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Aguard. Retirada</p>
          <p class="mt-2 text-3xl font-bold text-sky-400">{{ data.by_status.ready_for_pickup ?? 0 }}</p>
        </div>
      </div>

      <!-- Por status -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-400">Ordens por Status</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <RouterLink
            v-for="(count, status) in data.by_status"
            :key="status"
            :to="{ name: 'service-orders', query: { status } }"
            class="rounded-xl border border-white/10 p-4 transition hover:border-amber-500/30 hover:bg-amber-500/5"
          >
            <p class="text-xs text-stone-500">{{ statusLabels[status] ?? status }}</p>
            <p class="mt-1 text-2xl font-bold text-white">{{ count }}</p>
          </RouterLink>
        </div>
      </div>

      <!-- Carga por técnico + Pagamentos -->
      <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-400">Carga por Técnico</h2>
          <div v-if="data.technician_workload.length === 0" class="text-sm text-stone-500">Nenhuma OS atribuída.</div>
          <div v-else class="space-y-2">
            <div
              v-for="t in data.technician_workload"
              :key="t.assigned_membership_id"
              class="flex items-center justify-between rounded-xl border border-white/5 bg-black/20 px-4 py-2.5"
            >
              <span class="text-sm text-stone-300">{{ t.assigned_membership_name ?? 'Sem nome' }}</span>
              <span class="rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-300">
                {{ t.service_order_count }} OS
              </span>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-400">Pagamentos por Método</h2>
          <div v-if="Object.keys(data.received_amounts.by_payment_method).length === 0" class="text-sm text-stone-500">
            Nenhum pagamento registrado.
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="(cents, method) in data.received_amounts.by_payment_method"
              :key="method"
              class="flex items-center justify-between rounded-xl border border-white/5 bg-black/20 px-4 py-2.5"
            >
              <span class="text-sm text-stone-300">{{ paymentLabels[method] ?? method }}</span>
              <span class="text-sm font-semibold text-emerald-400">{{ formatMoney(cents) }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
