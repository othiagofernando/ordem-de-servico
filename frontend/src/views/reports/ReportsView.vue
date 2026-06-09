<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  downloadFinancialReportCsv,
  downloadServiceOrderReportCsv,
  getFinancialReport,
  getServiceOrderReport,
  type FinancialReportResponse,
  type ServiceOrderQueue,
  type ServiceOrderReportResponse,
} from '../../api/serviceOrders'

const activeTab = ref<'service_orders' | 'financial'>('service_orders')
const loading = ref(false)
const exporting = ref(false)
const error = ref('')

const serviceOrderFilters = ref<{
  q: string
  queue: '' | ServiceOrderQueue
  opened_from: string
  opened_to: string
}>({
  q: '',
  queue: '',
  opened_from: '',
  opened_to: '',
})

const financialFilters = ref<{
  q: string
  payment_method: string
  balance_state: '' | 'settled' | 'outstanding'
  received_from: string
  received_to: string
}>({
  q: '',
  payment_method: '',
  balance_state: '',
  received_from: '',
  received_to: '',
})

const serviceOrderReport = ref<ServiceOrderReportResponse | null>(null)
const financialReport = ref<FinancialReportResponse | null>(null)

const queueOptions: Array<{ value: '' | ServiceOrderQueue; label: string }> = [
  { value: '', label: 'Todas as filas' },
  { value: 'open', label: 'Abertas' },
  { value: 'pending_quote', label: 'Aguardando orçamento' },
  { value: 'in_progress', label: 'Em execução' },
  { value: 'ready_for_pickup', label: 'Prontas para retirada' },
  { value: 'terminal', label: 'Finalizadas' },
  { value: 'warranty_returns', label: 'Retornos em garantia' },
]

const paymentMethodOptions = [
  { value: '', label: 'Todos os meios' },
  { value: 'pix', label: 'PIX' },
  { value: 'cash', label: 'Dinheiro' },
  { value: 'credit_card', label: 'Cartão crédito' },
  { value: 'debit_card', label: 'Cartão débito' },
  { value: 'transfer', label: 'Transferência' },
  { value: 'check', label: 'Cheque' },
]

function formatMoney(cents: number) {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatDate(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('pt-BR')
}

async function loadServiceOrderReport() {
  loading.value = true
  error.value = ''

  try {
    serviceOrderReport.value = await getServiceOrderReport({
      q: serviceOrderFilters.value.q || undefined,
      queue: serviceOrderFilters.value.queue || undefined,
      opened_from: serviceOrderFilters.value.opened_from || undefined,
      opened_to: serviceOrderFilters.value.opened_to || undefined,
    })
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Falha ao carregar relatório de O.S.'
  } finally {
    loading.value = false
  }
}

async function loadFinancialReport() {
  loading.value = true
  error.value = ''

  try {
    financialReport.value = await getFinancialReport({
      q: financialFilters.value.q || undefined,
      payment_method: financialFilters.value.payment_method || undefined,
      balance_state: financialFilters.value.balance_state || undefined,
      received_from: financialFilters.value.received_from || undefined,
      received_to: financialFilters.value.received_to || undefined,
    })
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Falha ao carregar relatório financeiro.'
  } finally {
    loading.value = false
  }
}

async function exportCurrentReport() {
  exporting.value = true
  error.value = ''

  try {
    if (activeTab.value === 'service_orders') {
      await downloadServiceOrderReportCsv({
        q: serviceOrderFilters.value.q || undefined,
        queue: serviceOrderFilters.value.queue || undefined,
        opened_from: serviceOrderFilters.value.opened_from || undefined,
        opened_to: serviceOrderFilters.value.opened_to || undefined,
      })
    } else {
      await downloadFinancialReportCsv({
        q: financialFilters.value.q || undefined,
        payment_method: financialFilters.value.payment_method || undefined,
        balance_state: financialFilters.value.balance_state || undefined,
        received_from: financialFilters.value.received_from || undefined,
        received_to: financialFilters.value.received_to || undefined,
      })
    }
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Falha ao exportar CSV.'
  } finally {
    exporting.value = false
  }
}

function loadActiveTab() {
  if (activeTab.value === 'service_orders') {
    loadServiceOrderReport()
    return
  }

  loadFinancialReport()
}

onMounted(loadActiveTab)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Relatórios</h1>
        <p class="mt-1 text-sm text-stone-400">
          Consulta operacional e exportação CSV para O.S. e financeiro.
        </p>
      </div>

      <button
        class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300 transition hover:border-emerald-500/50 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="exporting"
        @click="exportCurrentReport"
      >
        {{ exporting ? 'Exportando…' : 'Exportar CSV' }}
      </button>
    </div>

    <div class="flex flex-wrap gap-2">
      <button
        :class="activeTab === 'service_orders' ? 'bg-amber-500 text-stone-950' : 'border border-white/10 text-stone-400 hover:border-amber-500/30 hover:text-amber-300'"
        class="rounded-full px-4 py-2 text-sm font-medium transition"
        @click="activeTab = 'service_orders'; loadServiceOrderReport()"
      >
        Relatório de O.S.
      </button>
      <button
        :class="activeTab === 'financial' ? 'bg-amber-500 text-stone-950' : 'border border-white/10 text-stone-400 hover:border-amber-500/30 hover:text-amber-300'"
        class="rounded-full px-4 py-2 text-sm font-medium transition"
        @click="activeTab = 'financial'; loadFinancialReport()"
      >
        Relatório financeiro
      </button>
    </div>

    <div v-if="error" class="rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300">
      {{ error }}
    </div>

    <template v-if="activeTab === 'service_orders'">
      <div class="grid gap-4 rounded-2xl border border-white/10 bg-white/5 p-6 lg:grid-cols-4">
        <input
          v-model="serviceOrderFilters.q"
          type="search"
          placeholder="Buscar cliente, número, serial…"
          class="rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/40"
        />
        <select
          v-model="serviceOrderFilters.queue"
          class="rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
        >
          <option v-for="option in queueOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <input
          v-model="serviceOrderFilters.opened_from"
          type="date"
          class="rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
        />
        <input
          v-model="serviceOrderFilters.opened_to"
          type="date"
          class="rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
        />
        <button
          class="rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 lg:col-span-4 lg:justify-self-start"
          :disabled="loading"
          @click="loadServiceOrderReport"
        >
          {{ loading ? 'Consultando…' : 'Consultar relatório de O.S.' }}
        </button>
      </div>

      <div v-if="serviceOrderReport" class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Total de ordens</p>
            <p class="mt-2 text-3xl font-bold text-white">{{ serviceOrderReport.meta.total }}</p>
          </div>
          <div
            v-for="(count, status) in serviceOrderReport.meta.by_status"
            :key="status"
            class="rounded-2xl border border-white/10 bg-white/5 p-5"
          >
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">{{ status }}</p>
            <p class="mt-2 text-2xl font-bold text-white">{{ count }}</p>
          </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-white/10">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-white/10 bg-white/5">
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Referência</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Cliente</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Equipamento</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Técnico</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Abertura</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="row in serviceOrderReport.data" :key="row.id" class="bg-black/10">
                <td class="px-4 py-3 text-stone-200">{{ row.order_number ?? row.reference }}</td>
                <td class="px-4 py-3 text-stone-300">{{ row.customer_name ?? '—' }}</td>
                <td class="px-4 py-3 text-stone-400">{{ row.equipment_summary || '—' }}</td>
                <td class="px-4 py-3 text-stone-300">{{ row.status }}</td>
                <td class="px-4 py-3 text-stone-400">{{ row.assigned_membership_name ?? '—' }}</td>
                <td class="px-4 py-3 text-stone-400">{{ formatDate(row.opened_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="grid gap-4 rounded-2xl border border-white/10 bg-white/5 p-6 lg:grid-cols-5">
        <input
          v-model="financialFilters.q"
          type="search"
          placeholder="Buscar cliente ou referência…"
          class="rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/40"
        />
        <select
          v-model="financialFilters.payment_method"
          class="rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
        >
          <option v-for="option in paymentMethodOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <select
          v-model="financialFilters.balance_state"
          class="rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
        >
          <option value="">Todos os saldos</option>
          <option value="outstanding">Com saldo pendente</option>
          <option value="settled">Quitadas</option>
        </select>
        <input
          v-model="financialFilters.received_from"
          type="date"
          class="rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
        />
        <input
          v-model="financialFilters.received_to"
          type="date"
          class="rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
        />
        <button
          class="rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 lg:col-span-5 lg:justify-self-start"
          :disabled="loading"
          @click="loadFinancialReport"
        >
          {{ loading ? 'Consultando…' : 'Consultar relatório financeiro' }}
        </button>
      </div>

      <div v-if="financialReport" class="space-y-4">
        <div class="grid gap-4 md:grid-cols-3">
          <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Cobrado</p>
            <p class="mt-2 text-2xl font-bold text-white">{{ formatMoney(financialReport.meta.totals.total_charged_cents) }}</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Pago</p>
            <p class="mt-2 text-2xl font-bold text-emerald-400">{{ formatMoney(financialReport.meta.totals.total_paid_cents) }}</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Saldo pendente</p>
            <p class="mt-2 text-2xl font-bold text-amber-300">{{ formatMoney(financialReport.meta.totals.outstanding_balance_cents) }}</p>
          </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-white/10">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-white/10 bg-white/5">
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Referência</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Cliente</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Cobrado</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Pago</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Saldo</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Último pagamento</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="row in financialReport.data" :key="row.service_order_id" class="bg-black/10">
                <td class="px-4 py-3 text-stone-200">{{ row.order_number ?? row.reference }}</td>
                <td class="px-4 py-3 text-stone-300">{{ row.customer_name ?? '—' }}</td>
                <td class="px-4 py-3 text-stone-300">{{ formatMoney(row.total_charged_cents) }}</td>
                <td class="px-4 py-3 text-emerald-400">{{ formatMoney(row.total_paid_cents) }}</td>
                <td class="px-4 py-3 text-amber-300">{{ formatMoney(row.outstanding_balance_cents) }}</td>
                <td class="px-4 py-3 text-stone-400">{{ formatDate(row.last_payment_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
