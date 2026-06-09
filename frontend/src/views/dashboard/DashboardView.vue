<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { getOverview, type OverviewData, type ServiceOrderQueue } from '../../api/serviceOrders'

const data = ref<OverviewData | null>(null)
const loading = ref(true)
const error = ref(false)

const statusLabels: Record<string, string> = {
  draft: 'Rascunho',
  open: 'Aberta',
  in_progress: 'Em Execução',
  completed: 'Concluída',
  ready_for_pickup: 'Aguardando retirada',
  delivered: 'Entregue',
  cancelled: 'Cancelada',
  no_repair: 'Sem reparo',
}

const paymentLabels: Record<string, string> = {
  pix: 'PIX',
  cash: 'Dinheiro',
  credit_card: 'Cartão crédito',
  debit_card: 'Cartão débito',
  transfer: 'Transferência',
  check: 'Cheque',
}

const queueCards: Array<{
  key: ServiceOrderQueue | 'drafts'
  label: string
  helper: string
  accent: string
}> = [
  { key: 'open', label: 'Triagem aberta', helper: 'OS recém-abertas aguardando avanço.', accent: 'text-sky-300' },
  { key: 'pending_quote', label: 'Aguardando orçamento', helper: 'Ordens com revisão pendente.', accent: 'text-amber-300' },
  { key: 'in_progress', label: 'Em execução', helper: 'Ordens já em bancada ou atendimento.', accent: 'text-orange-300' },
  { key: 'ready_for_pickup', label: 'Prontas para retirada', helper: 'Clientes já podem ser acionados.', accent: 'text-emerald-300' },
  { key: 'warranty_returns', label: 'Retornos em garantia', helper: 'Casos reincidentes que exigem atenção.', accent: 'text-violet-300' },
  { key: 'terminal', label: 'Finalizadas', helper: 'Entregues, canceladas ou sem reparo.', accent: 'text-stone-300' },
]

function formatMoney(cents: number) {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function queueTotal(key: keyof OverviewData['queues']) {
  return data.value?.queues[key] ?? 0
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
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Dashboard operacional</h1>
        <p class="mt-1 text-sm text-stone-400">
          Visão resumida da fila atual, gargalos imediatos e capacidade do time.
        </p>
      </div>

      <RouterLink
        :to="{ name: 'service-orders.create' }"
        class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
      >
        + Nova OS
      </RouterLink>
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>
    <div v-else-if="error" class="rounded-xl border border-red-500/20 bg-red-500/10 p-6 text-red-300">
      Não foi possível carregar os dados.
    </div>

    <template v-else-if="data">
      <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Escopo atual</p>
        <p class="mt-2 text-sm text-stone-200">
          <span v-if="data.scope.mode === 'tenant'">
            Você está vendo a operação completa da empresa.
          </span>
          <span v-else>
            Você está vendo apenas as O.S. atribuídas a {{ data.scope.membership_name ?? 'você' }}.
          </span>
        </p>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Total visível</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ data.totals.service_orders }}</p>
        </div>
        <RouterLink
          :to="{ name: 'service-orders', query: { queue: 'open' } }"
          class="rounded-2xl border border-sky-500/20 bg-sky-500/5 p-5 transition hover:border-sky-500/40"
        >
          <p class="text-xs font-semibold uppercase tracking-widest text-sky-300">Sem técnico</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ data.attention.unassigned_open }}</p>
          <p class="mt-2 text-sm text-stone-400">OS abertas sem responsável atribuído.</p>
        </RouterLink>
        <RouterLink
          :to="{ name: 'service-orders', query: { queue: 'pending_quote' } }"
          class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5 transition hover:border-amber-500/40"
        >
          <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">Orçamento pendente</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ data.attention.pending_quote }}</p>
          <p class="mt-2 text-sm text-stone-400">Ordens paradas aguardando definição comercial.</p>
        </RouterLink>
        <RouterLink
          :to="{ name: 'service-orders', query: { queue: 'ready_for_pickup' } }"
          class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5 transition hover:border-emerald-500/40"
        >
          <p class="text-xs font-semibold uppercase tracking-widest text-emerald-300">Prontas para retirada</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ data.attention.ready_for_pickup }}</p>
          <p class="mt-2 text-sm text-stone-400">Ordens concluídas aguardando entrega ao cliente.</p>
        </RouterLink>
      </div>

      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Filas operacionais</h2>
            <p class="mt-1 text-sm text-stone-500">Atalhos para as principais filas do dia a dia.</p>
          </div>
          <RouterLink
            :to="{ name: 'service-orders' }"
            class="text-sm font-medium text-amber-300 transition hover:text-amber-200"
          >
            Ver todas
          </RouterLink>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          <RouterLink
            v-for="queue in queueCards"
            :key="queue.key"
            :to="{ name: 'service-orders', query: { queue: queue.key } }"
            class="rounded-xl border border-white/10 bg-black/20 p-4 transition hover:border-amber-500/30 hover:bg-amber-500/5"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-white">{{ queue.label }}</p>
                <p class="mt-1 text-sm text-stone-500">{{ queue.helper }}</p>
              </div>
              <span :class="queue.accent" class="text-3xl font-bold">
                {{ queueTotal(queue.key) }}
              </span>
            </div>
          </RouterLink>
        </div>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6 xl:col-span-1">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-400">Ordens por status</h2>
          <div class="space-y-2">
            <div
              v-for="(count, status) in data.by_status"
              :key="status"
              class="flex items-center justify-between rounded-xl border border-white/5 bg-black/20 px-4 py-2.5"
            >
              <span class="text-sm text-stone-300">{{ statusLabels[status] ?? status }}</span>
              <span class="rounded-full bg-white/5 px-2.5 py-0.5 text-xs font-semibold text-stone-200">{{ count }}</span>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-white/5 p-6 xl:col-span-1">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-400">Carga por técnico</h2>
          <div v-if="data.technician_workload.length === 0" class="text-sm text-stone-500">Nenhuma O.S. atribuída.</div>
          <div v-else class="space-y-2">
            <div
              v-for="tech in data.technician_workload"
              :key="tech.assigned_membership_id"
              class="flex items-center justify-between rounded-xl border border-white/5 bg-black/20 px-4 py-2.5"
            >
              <span class="text-sm text-stone-300">{{ tech.assigned_membership_name ?? 'Sem nome' }}</span>
              <span class="rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-300">
                {{ tech.service_order_count }} O.S.
              </span>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-white/5 p-6 xl:col-span-1">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-400">Resumo financeiro</h2>
          <div class="rounded-xl border border-white/5 bg-black/20 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Recebido no escopo visível</p>
            <p class="mt-2 text-2xl font-bold text-emerald-400">
              {{ formatMoney(data.received_amounts.total_received_cents) }}
            </p>
          </div>

          <div class="mt-3 space-y-2">
            <div
              v-for="(cents, method) in data.received_amounts.by_payment_method"
              :key="method"
              class="flex items-center justify-between rounded-xl border border-white/5 bg-black/20 px-4 py-2.5"
            >
              <span class="text-sm text-stone-300">{{ paymentLabels[method] ?? method }}</span>
              <span class="text-sm font-semibold text-emerald-400">{{ formatMoney(cents) }}</span>
            </div>
            <div
              v-if="Object.keys(data.received_amounts.by_payment_method).length === 0"
              class="text-sm text-stone-500"
            >
              Nenhum pagamento registrado.
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
