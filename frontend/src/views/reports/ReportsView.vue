<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useSortable } from '../../composables/useSortable'
import ThSort from '../../components/ThSort.vue'
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

const soRows = computed(() => serviceOrderReport.value?.data ?? [])
const finRows = computed(() => financialReport.value?.data ?? [])

const { sorted: sortedSoRows, toggleSort: soToggle, sortState: soState } = useSortable(soRows, {
  reference: (r) => (r as { order_number?: string; reference: string }).order_number ?? (r as { reference: string }).reference,
})
const { sorted: sortedFinRows, toggleSort: finToggle, sortState: finState } = useSortable(finRows)

const queueOptions: Array<{ value: '' | ServiceOrderQueue; label: string }> = [
  { value: '', label: 'Todas as filas' },
  { value: 'open', label: 'Abertas' },
  { value: 'pending_quote', label: 'Aguardando orçamento' },
  { value: 'in_progress', label: 'Em execução' },
  { value: 'ready_for_pickup', label: 'Prontas para retirada' },
  { value: 'warranty_returns', label: 'Retornos em garantia' },
  { value: 'terminal', label: 'Finalizadas' },
]

const paymentMethodOptions = [
  { value: '', label: 'Todos os meios' },
  { value: 'pix', label: 'PIX' },
  { value: 'cash', label: 'Dinheiro' },
  { value: 'credit_card', label: 'Cartão crédito' },
  { value: 'debit_card', label: 'Cartão débito' },
  { value: 'bank_transfer', label: 'Transferência' },
  { value: 'other', label: 'Outro' },
]

const statusLabels: Record<string, string> = {
  draft: 'Rascunho',
  open: 'Aberta',
  in_progress: 'Em execução',
  completed: 'Concluída',
  ready_for_pickup: 'Pronta para retirada',
  delivered: 'Entregue',
  cancelled: 'Cancelada',
  no_repair: 'Sem reparo',
}

const quoteDecisionLabels: Record<string, string> = {
  pending: 'Orçamento pendente',
  approved: 'Orçamento aprovado',
  rejected: 'Orçamento recusado',
  expired: 'Orçamento expirado',
}

const warrantyDecisionLabels: Record<string, string> = {
  pending_inspection: 'Garantia pendente',
  covered: 'Garantia coberta',
  not_covered: 'Garantia não coberta',
}

const activeServiceOrderFilters = computed(() =>
  [
    serviceOrderFilters.value.q && `Busca: ${serviceOrderFilters.value.q}`,
    serviceOrderFilters.value.queue && `Fila: ${queueOptions.find((option) => option.value === serviceOrderFilters.value.queue)?.label}`,
    serviceOrderFilters.value.opened_from && `Abertas a partir de ${formatDate(serviceOrderFilters.value.opened_from)}`,
    serviceOrderFilters.value.opened_to && `Abertas até ${formatDate(serviceOrderFilters.value.opened_to)}`,
  ].filter(Boolean) as string[],
)

const activeFinancialFilters = computed(() =>
  [
    financialFilters.value.q && `Busca: ${financialFilters.value.q}`,
    financialFilters.value.payment_method && `Pagamento: ${paymentMethodOptions.find((option) => option.value === financialFilters.value.payment_method)?.label}`,
    financialFilters.value.balance_state === 'outstanding' && 'Somente com saldo pendente',
    financialFilters.value.balance_state === 'settled' && 'Somente quitadas',
    financialFilters.value.received_from && `Recebimentos a partir de ${formatDate(financialFilters.value.received_from)}`,
    financialFilters.value.received_to && `Recebimentos até ${formatDate(financialFilters.value.received_to)}`,
  ].filter(Boolean) as string[],
)

function formatMoney(cents: number) {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatDate(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('pt-BR')
}

function resetServiceOrderFilters() {
  serviceOrderFilters.value = {
    q: '',
    queue: '',
    opened_from: '',
    opened_to: '',
  }
  loadServiceOrderReport()
}

function resetFinancialFilters() {
  financialFilters.value = {
    q: '',
    payment_method: '',
    balance_state: '',
    received_from: '',
    received_to: '',
  }
  loadFinancialReport()
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
        <label class="space-y-1">
          <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Busca</span>
          <input
            v-model="serviceOrderFilters.q"
            type="search"
            placeholder="Cliente, número, serial…"
            class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/40"
          />
        </label>
        <label class="space-y-1">
          <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Fila</span>
          <select
            v-model="serviceOrderFilters.queue"
            class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
          >
            <option v-for="option in queueOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </label>
        <label class="space-y-1">
          <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Abertas de</span>
          <input
            v-model="serviceOrderFilters.opened_from"
            type="date"
            class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
          />
        </label>
        <label class="space-y-1">
          <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Abertas até</span>
          <input
            v-model="serviceOrderFilters.opened_to"
            type="date"
            class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
          />
        </label>
        <div class="flex flex-wrap gap-2 lg:col-span-4">
          <button
            class="rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
            :disabled="loading"
            @click="loadServiceOrderReport"
          >
            {{ loading ? 'Consultando…' : 'Consultar relatório de O.S.' }}
          </button>
          <button
            class="rounded-xl border border-white/10 px-4 py-2.5 text-sm text-stone-300 transition hover:border-white/20 hover:text-white"
            :disabled="loading"
            @click="resetServiceOrderFilters"
          >
            Limpar filtros
          </button>
        </div>
      </div>

      <div v-if="serviceOrderReport" class="space-y-4">
        <div v-if="activeServiceOrderFilters.length > 0" class="flex flex-wrap gap-2">
          <span
            v-for="filter in activeServiceOrderFilters"
            :key="filter"
            class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-stone-300"
          >
            {{ filter }}
          </span>
        </div>

        <p class="text-sm text-stone-500">{{ serviceOrderReport.meta.total }} ordem(ns) encontrada(s).</p>

        <div v-if="serviceOrderReport.data.length === 0" class="rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-stone-400">
          Nenhuma O.S. encontrada para os filtros atuais.
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-white/10">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-white/10 bg-white/5">
                <ThSort :state="soState('reference')" @click="soToggle('reference')">Referência</ThSort>
                <ThSort :state="soState('customer_name')" @click="soToggle('customer_name')">Cliente</ThSort>
                <ThSort :state="soState('equipment_summary')" @click="soToggle('equipment_summary')">Equipamento</ThSort>
                <ThSort :state="soState('status')" @click="soToggle('status')">Status</ThSort>
                <ThSort :state="soState('latest_quote_status')" @click="soToggle('latest_quote_status')">Comercial</ThSort>
                <ThSort :state="soState('assigned_membership_name')" @click="soToggle('assigned_membership_name')">Técnico</ThSort>
                <ThSort :state="soState('opened_at')" @click="soToggle('opened_at')">Abertura</ThSort>
                <th class="px-4 py-3" />
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="row in sortedSoRows" :key="row.id" class="bg-black/10">
                <td class="px-4 py-3 text-stone-200">{{ row.order_number ?? row.reference }}</td>
                <td class="px-4 py-3 text-stone-300">{{ row.customer_name ?? '—' }}</td>
                <td class="px-4 py-3 text-stone-400">{{ row.equipment_summary || '—' }}</td>
                <td class="px-4 py-3 text-stone-300">{{ statusLabels[row.status] ?? row.status }}</td>
                <td class="px-4 py-3 text-stone-400">
                  {{ warrantyDecisionLabels[row.warranty_decision_status ?? ''] ?? quoteDecisionLabels[row.latest_quote_status ?? ''] ?? '—' }}
                </td>
                <td class="px-4 py-3 text-stone-400">{{ row.assigned_membership_name ?? '—' }}</td>
                <td class="px-4 py-3 text-stone-400">{{ formatDate(row.opened_at) }}</td>
                <td class="px-4 py-3 text-right">
                  <RouterLink :to="{ name: 'service-orders.detail', params: { id: row.id } }" class="text-xs text-amber-300 hover:text-amber-200">
                    Abrir
                  </RouterLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="grid gap-4 rounded-2xl border border-white/10 bg-white/5 p-6 lg:grid-cols-5">
        <label class="space-y-1">
          <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Busca</span>
          <input
            v-model="financialFilters.q"
            type="search"
            placeholder="Cliente ou referência…"
            class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/40"
          />
        </label>
        <label class="space-y-1">
          <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Pagamento</span>
          <select
            v-model="financialFilters.payment_method"
            class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
          >
            <option v-for="option in paymentMethodOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </label>
        <label class="space-y-1">
          <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Saldo</span>
          <select
            v-model="financialFilters.balance_state"
            class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
          >
            <option value="">Todos os saldos</option>
            <option value="outstanding">Com saldo pendente</option>
            <option value="settled">Quitadas</option>
          </select>
        </label>
        <label class="space-y-1">
          <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Recebimentos de</span>
          <input
            v-model="financialFilters.received_from"
            type="date"
            class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
          />
        </label>
        <label class="space-y-1">
          <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Recebimentos até</span>
          <input
            v-model="financialFilters.received_to"
            type="date"
            class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
          />
        </label>
        <div class="flex flex-wrap gap-2 lg:col-span-5">
          <button
            class="rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
            :disabled="loading"
            @click="loadFinancialReport"
          >
            {{ loading ? 'Consultando…' : 'Consultar relatório financeiro' }}
          </button>
          <button
            class="rounded-xl border border-white/10 px-4 py-2.5 text-sm text-stone-300 transition hover:border-white/20 hover:text-white"
            :disabled="loading"
            @click="resetFinancialFilters"
          >
            Limpar filtros
          </button>
        </div>
      </div>

      <div v-if="financialReport" class="space-y-4">
        <div v-if="activeFinancialFilters.length > 0" class="flex flex-wrap gap-2">
          <span
            v-for="filter in activeFinancialFilters"
            :key="filter"
            class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-stone-300"
          >
            {{ filter }}
          </span>
        </div>

        <div class="flex flex-wrap gap-x-6 gap-y-1 rounded-xl border border-white/10 bg-white/5 px-5 py-3.5 text-sm">
          <span class="text-stone-500">{{ financialReport.meta.total }} ordens</span>
          <span class="text-stone-400">Cobrado: <strong class="text-white">{{ formatMoney(financialReport.meta.totals.total_charged_cents) }}</strong></span>
          <span class="text-stone-400">Pago: <strong class="text-emerald-400">{{ formatMoney(financialReport.meta.totals.total_paid_cents) }}</strong></span>
          <span class="text-stone-400">Saldo: <strong class="text-amber-300">{{ formatMoney(financialReport.meta.totals.outstanding_balance_cents) }}</strong></span>
        </div>

        <div v-if="financialReport.data.length === 0" class="rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-stone-400">
          Nenhum lançamento financeiro encontrado para os filtros atuais.
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-white/10">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-white/10 bg-white/5">
                <ThSort :state="finState('reference')" @click="finToggle('reference')">Referência</ThSort>
                <ThSort :state="finState('customer_name')" @click="finToggle('customer_name')">Cliente</ThSort>
                <ThSort :state="finState('status')" @click="finToggle('status')">Status</ThSort>
                <ThSort :state="finState('total_charged_cents')" @click="finToggle('total_charged_cents')">Cobrado</ThSort>
                <ThSort :state="finState('total_paid_cents')" @click="finToggle('total_paid_cents')">Pago</ThSort>
                <ThSort :state="finState('outstanding_balance_cents')" @click="finToggle('outstanding_balance_cents')">Saldo</ThSort>
                <ThSort :state="finState('last_payment_at')" @click="finToggle('last_payment_at')">Último pagamento</ThSort>
                <th class="px-4 py-3" />
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="row in sortedFinRows" :key="row.service_order_id" class="bg-black/10">
                <td class="px-4 py-3 text-stone-200">{{ row.order_number ?? row.reference }}</td>
                <td class="px-4 py-3 text-stone-300">{{ row.customer_name ?? '—' }}</td>
                <td class="px-4 py-3 text-stone-400">{{ statusLabels[row.status] ?? row.status }}</td>
                <td class="px-4 py-3 text-stone-300">{{ formatMoney(row.total_charged_cents) }}</td>
                <td class="px-4 py-3 text-emerald-400">{{ formatMoney(row.total_paid_cents) }}</td>
                <td class="px-4 py-3 text-amber-300">{{ formatMoney(row.outstanding_balance_cents) }}</td>
                <td class="px-4 py-3 text-stone-400">{{ formatDate(row.last_payment_at) }}</td>
                <td class="px-4 py-3 text-right">
                  <RouterLink :to="{ name: 'service-orders.detail', params: { id: row.service_order_id } }" class="text-xs text-amber-300 hover:text-amber-200">
                    Abrir
                  </RouterLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
