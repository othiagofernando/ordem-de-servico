<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  LineElement,
  PointElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'
import { Bar, Doughnut } from 'vue-chartjs'
import { useSortable } from '../../composables/useSortable'
import ThSort from '../../components/ThSort.vue'
import AppPagination from '../../components/AppPagination.vue'
import {
  downloadFinancialReportCsv,
  downloadServiceOrderReportCsv,
  downloadTechnicianReportCsv,
  getFinancialReport,
  getOverview,
  getServiceOrderReport,
  getTechnicianReport,
  getTrends,
  type FinancialReportResponse,
  type OverviewData,
  type PaginationMeta,
  type ServiceOrderQueue,
  type ServiceOrderReportResponse,
  type TechnicianReportRow,
  type TrendsMonth,
} from '../../api/serviceOrders'
import { useRouter } from 'vue-router'
import { useTenantRouting } from '../../composables/useTenantRouting'
import { useThemeStore } from '../../stores/theme'
import { useAuthStore } from '../../stores/auth'

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  LineElement,
  PointElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler,
)

const { tenantRoute } = useTenantRouting()
const theme = useThemeStore()
const auth = useAuthStore()
const router = useRouter()

const activeTab = ref<'overview' | 'service_orders' | 'financial' | 'technician'>('overview')
const loading = ref(false)
const exporting = ref(false)
const error = ref('')

// Overview tab state
const overviewData = ref<OverviewData | null>(null)
const trendsData = ref<TrendsMonth[]>([])
const overviewLoading = ref(false)

// Service Orders tab state
const soPage = ref(1)
const soPerPage = ref(15)
const finPage = ref(1)
const finPerPage = ref(15)

const emptyMeta: PaginationMeta = { total: 0, per_page: 15, current_page: 1, last_page: 1, from: 0, to: 0 }
const soMeta = ref<PaginationMeta>({ ...emptyMeta })
const finMeta = ref<PaginationMeta>({ ...emptyMeta })

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

// Technician tab state
const technicianFilters = ref<{ opened_from: string; opened_to: string }>({
  opened_from: '',
  opened_to: '',
})
const technicianRows = ref<TechnicianReportRow[]>([])
const technicianLoading = ref(false)

const serviceOrderReport = ref<ServiceOrderReportResponse | null>(null)
const financialReport = ref<FinancialReportResponse | null>(null)

const soRows = computed(() => serviceOrderReport.value?.data ?? [])
const finRows = computed(() => financialReport.value?.data ?? [])
const techRows = computed(() => technicianRows.value)

const { sorted: sortedSoRows, toggleSort: soToggle, sortState: soState } = useSortable(soRows, {
  reference: (r) => (r.order_number !== null ? String(r.order_number) : r.reference),
})
const { sorted: sortedFinRows, toggleSort: finToggle, sortState: finState } = useSortable(finRows)
const { sorted: sortedTechRows, toggleSort: techToggle, sortState: techState } = useSortable(techRows)

// Chart theme
const isDark = computed(() => theme.isDark)
const gridColor = computed(() => isDark.value ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)')
const textColor = computed(() => isDark.value ? '#78716c' : '#57534e')

// Trends chart data
const trendsChartData = computed(() => ({
  labels: trendsData.value.map((m) => m.label),
  datasets: [
    {
      label: 'OS Abertas',
      data: trendsData.value.map((m) => m.opened),
      backgroundColor: 'rgba(245,158,11,0.7)',
      borderColor: 'rgba(245,158,11,1)',
      borderWidth: 2,
      borderRadius: 4,
    },
    {
      label: 'OS Concluídas',
      data: trendsData.value.map((m) => m.completed),
      backgroundColor: 'rgba(52,211,153,0.7)',
      borderColor: 'rgba(52,211,153,1)',
      borderWidth: 2,
      borderRadius: 4,
    },
  ],
}))

const trendsChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      labels: { color: textColor.value },
    },
  },
  scales: {
    x: {
      grid: { color: gridColor.value },
      ticks: { color: textColor.value },
    },
    y: {
      grid: { color: gridColor.value },
      ticks: { color: textColor.value },
      beginAtZero: true,
    },
  },
}))

// Status chart data
const statusChartData = computed(() => {
  const byStatus = overviewData.value?.by_status ?? {}
  const statusLabelMap: Record<string, string> = {
    draft: 'Rascunho',
    open: 'Aberta',
    in_progress: 'Em execução',
    completed: 'Concluída',
    ready_for_pickup: 'Pronta p/ retirada',
    delivered: 'Entregue',
    cancelled: 'Cancelada',
    no_repair: 'Sem reparo',
  }
  const colors = [
    'rgba(245,158,11,0.8)',
    'rgba(52,211,153,0.8)',
    'rgba(96,165,250,0.8)',
    'rgba(167,139,250,0.8)',
    'rgba(251,191,36,0.8)',
    'rgba(34,211,238,0.8)',
    'rgba(248,113,113,0.8)',
    'rgba(163,163,163,0.8)',
  ]
  const keys = Object.keys(byStatus)
  return {
    labels: keys.map((k) => statusLabelMap[k] ?? k),
    datasets: [
      {
        data: keys.map((k) => byStatus[k]),
        backgroundColor: keys.map((_, i) => colors[i % colors.length]),
        borderWidth: 0,
      },
    ],
  }
})

// Quote outcomes chart data
const quoteChartData = computed(() => {
  const outcomes = overviewData.value?.quote_outcomes ?? {}
  const outcomeLabels: Record<string, string> = {
    pending: 'Pendente',
    approved: 'Aprovado',
    rejected: 'Recusado',
    expired: 'Expirado',
  }
  const colors = [
    'rgba(245,158,11,0.8)',
    'rgba(52,211,153,0.8)',
    'rgba(248,113,113,0.8)',
    'rgba(163,163,163,0.8)',
  ]
  const keys = Object.keys(outcomes)
  return {
    labels: keys.map((k) => outcomeLabels[k] ?? k),
    datasets: [
      {
        data: keys.map((k) => outcomes[k]),
        backgroundColor: keys.map((_, i) => colors[i % colors.length]),
        borderWidth: 0,
      },
    ],
  }
})

// Technician workload chart data
const workloadChartData = computed(() => {
  const workload = overviewData.value?.technician_workload ?? []
  return {
    labels: workload.map((w) => w.assigned_membership_name ?? 'Desconhecido'),
    datasets: [
      {
        label: 'OS Atribuídas',
        data: workload.map((w) => w.service_order_count),
        backgroundColor: 'rgba(96,165,250,0.8)',
        borderColor: 'rgba(96,165,250,1)',
        borderWidth: 2,
        borderRadius: 4,
      },
    ],
  }
})

const doughnutOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'right' as const,
      labels: { color: textColor.value, boxWidth: 12, font: { size: 11 } },
    },
  },
}))

const workloadChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y' as const,
  plugins: {
    legend: { display: false },
  },
  scales: {
    x: {
      grid: { color: gridColor.value },
      ticks: { color: textColor.value },
      beginAtZero: true,
    },
    y: {
      grid: { color: gridColor.value },
      ticks: { color: textColor.value },
    },
  },
}))

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

// Technician totals
const technicianTotals = computed(() => {
  const rows = technicianRows.value
  return {
    total: rows.reduce((s, r) => s + r.total, 0),
    in_progress: rows.reduce((s, r) => s + r.in_progress, 0),
    completed: rows.reduce((s, r) => s + r.completed, 0),
    cancelled: rows.reduce((s, r) => s + r.cancelled, 0),
    no_repair: rows.reduce((s, r) => s + r.no_repair, 0),
    pending: rows.reduce((s, r) => s + r.pending, 0),
    total_received_cents: rows.reduce((s, r) => s + r.total_received_cents, 0),
  }
})

function formatMoney(cents: number) {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatDate(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('pt-BR')
}

function resetServiceOrderFilters() {
  serviceOrderFilters.value = { q: '', queue: '', opened_from: '', opened_to: '' }
  loadServiceOrderReport(true)
}

function resetFinancialFilters() {
  financialFilters.value = { q: '', payment_method: '', balance_state: '', received_from: '', received_to: '' }
  loadFinancialReport(true)
}

async function loadOverview() {
  overviewLoading.value = true
  error.value = ''

  try {
    const [overviewRes, trendsRes] = await Promise.all([
      getOverview(),
      getTrends(6),
    ])
    overviewData.value = overviewRes.data
    trendsData.value = trendsRes.data.months
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Falha ao carregar visão geral.'
  } finally {
    overviewLoading.value = false
  }
}

async function loadTechnicianReport() {
  technicianLoading.value = true
  error.value = ''

  try {
    const res = await getTechnicianReport({
      opened_from: technicianFilters.value.opened_from || undefined,
      opened_to: technicianFilters.value.opened_to || undefined,
    })
    technicianRows.value = res.data
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Falha ao carregar relatório por técnico.'
  } finally {
    technicianLoading.value = false
  }
}

async function loadServiceOrderReport(resetPage = false) {
  if (resetPage) soPage.value = 1
  loading.value = true
  error.value = ''

  try {
    serviceOrderReport.value = await getServiceOrderReport({
      q: serviceOrderFilters.value.q || undefined,
      queue: serviceOrderFilters.value.queue || undefined,
      opened_from: serviceOrderFilters.value.opened_from || undefined,
      opened_to: serviceOrderFilters.value.opened_to || undefined,
      page: soPage.value,
      per_page: soPerPage.value,
    })
    soMeta.value = serviceOrderReport.value.meta
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Falha ao carregar relatório de O.S.'
  } finally {
    loading.value = false
  }
}

async function loadFinancialReport(resetPage = false) {
  if (resetPage) finPage.value = 1
  loading.value = true
  error.value = ''

  try {
    financialReport.value = await getFinancialReport({
      q: financialFilters.value.q || undefined,
      payment_method: financialFilters.value.payment_method || undefined,
      balance_state: financialFilters.value.balance_state || undefined,
      received_from: financialFilters.value.received_from || undefined,
      received_to: financialFilters.value.received_to || undefined,
      page: finPage.value,
      per_page: finPerPage.value,
    })
    finMeta.value = financialReport.value.meta
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
    } else if (activeTab.value === 'financial') {
      await downloadFinancialReportCsv({
        q: financialFilters.value.q || undefined,
        payment_method: financialFilters.value.payment_method || undefined,
        balance_state: financialFilters.value.balance_state || undefined,
        received_from: financialFilters.value.received_from || undefined,
        received_to: financialFilters.value.received_to || undefined,
      })
    } else if (activeTab.value === 'technician') {
      await downloadTechnicianReportCsv({
        opened_from: technicianFilters.value.opened_from || undefined,
        opened_to: technicianFilters.value.opened_to || undefined,
      })
    }
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Falha ao exportar CSV.'
  } finally {
    exporting.value = false
  }
}

function openPrintReport() {
  const slug = auth.company?.slug ?? ''
  const company = auth.company?.name ?? ''
  const query: Record<string, string> = { company }

  if (activeTab.value === 'financial') {
    query.type = 'financial'
    if (financialFilters.value.q) query.q = financialFilters.value.q
    if (financialFilters.value.payment_method) query.payment_method = financialFilters.value.payment_method
    if (financialFilters.value.balance_state) query.balance_state = financialFilters.value.balance_state
    if (financialFilters.value.received_from) query.received_from = financialFilters.value.received_from
    if (financialFilters.value.received_to) query.received_to = financialFilters.value.received_to
  } else if (activeTab.value === 'technician') {
    query.type = 'technician'
    if (technicianFilters.value.opened_from) query.opened_from = technicianFilters.value.opened_from
    if (technicianFilters.value.opened_to) query.opened_to = technicianFilters.value.opened_to
  } else {
    query.type = 'service_orders'
    if (serviceOrderFilters.value.q) query.q = serviceOrderFilters.value.q
    if (serviceOrderFilters.value.queue) query.queue = serviceOrderFilters.value.queue
    if (serviceOrderFilters.value.opened_from) query.opened_from = serviceOrderFilters.value.opened_from
    if (serviceOrderFilters.value.opened_to) query.opened_to = serviceOrderFilters.value.opened_to
  }

  const resolved = router.resolve({ name: 'reports.print', params: { tenantSlug: slug }, query })
  window.open(resolved.href, '_blank')
}

function loadActiveTab() {
  if (activeTab.value === 'overview') {
    loadOverview()
  } else if (activeTab.value === 'service_orders') {
    loadServiceOrderReport()
  } else if (activeTab.value === 'financial') {
    loadFinancialReport()
  } else if (activeTab.value === 'technician') {
    loadTechnicianReport()
  }
}

watch(activeTab, loadActiveTab)
onMounted(loadActiveTab)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Relatórios</h1>
        <p class="mt-1 text-sm text-stone-400">
          Dashboard visual, relatórios operacionais e exportação CSV.
        </p>
      </div>

      <div v-if="activeTab !== 'overview'" class="flex gap-2">
        <button
          class="rounded-xl border border-sky-500/30 bg-sky-500/10 px-4 py-2 text-sm font-semibold text-sky-300 transition hover:border-sky-500/50"
          @click="openPrintReport"
        >
          Exportar PDF
        </button>
        <button
          class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300 transition hover:border-emerald-500/50 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="exporting"
          @click="exportCurrentReport"
        >
          {{ exporting ? 'Exportando…' : 'Exportar CSV' }}
        </button>
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      <button
        :class="activeTab === 'overview' ? 'bg-amber-500 text-stone-950' : 'border border-white/10 text-stone-400 hover:border-amber-500/30 hover:text-amber-300'"
        class="rounded-full px-4 py-2 text-sm font-medium transition"
        @click="activeTab = 'overview'"
      >
        Visão Geral
      </button>
      <button
        :class="activeTab === 'service_orders' ? 'bg-amber-500 text-stone-950' : 'border border-white/10 text-stone-400 hover:border-amber-500/30 hover:text-amber-300'"
        class="rounded-full px-4 py-2 text-sm font-medium transition"
        @click="activeTab = 'service_orders'"
      >
        Relatório de O.S.
      </button>
      <button
        :class="activeTab === 'financial' ? 'bg-amber-500 text-stone-950' : 'border border-white/10 text-stone-400 hover:border-amber-500/30 hover:text-amber-300'"
        class="rounded-full px-4 py-2 text-sm font-medium transition"
        @click="activeTab = 'financial'"
      >
        Relatório financeiro
      </button>
      <button
        :class="activeTab === 'technician' ? 'bg-amber-500 text-stone-950' : 'border border-white/10 text-stone-400 hover:border-amber-500/30 hover:text-amber-300'"
        class="rounded-full px-4 py-2 text-sm font-medium transition"
        @click="activeTab = 'technician'"
      >
        Por Técnico
      </button>
    </div>

    <div v-if="error" class="rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300">
      {{ error }}
    </div>

    <!-- ======================== VISÃO GERAL ======================== -->
    <template v-if="activeTab === 'overview'">
      <div v-if="overviewLoading" class="py-12 text-center text-sm text-stone-500">
        Carregando visão geral…
      </div>

      <template v-else-if="overviewData">
        <!-- Métricas atuais -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Total de O.S.</p>
            <p class="mt-2 text-3xl font-bold text-white">{{ overviewData.totals.service_orders }}</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Receita Recebida</p>
            <p class="mt-2 text-2xl font-bold text-emerald-400">{{ formatMoney(overviewData.received_amounts.total_received_cents) }}</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">OS Pendentes</p>
            <p class="mt-2 text-3xl font-bold text-amber-300">{{ (overviewData.queues['open'] ?? 0) + (overviewData.queues['in_progress'] ?? 0) }}</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">OS Prontas</p>
            <p class="mt-2 text-3xl font-bold text-blue-400">{{ overviewData.attention.ready_for_pickup }}</p>
          </div>
        </div>

        <!-- Tendência Mensal -->
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Tendência Mensal</h2>
          <div style="height: 260px">
            <Bar :data="trendsChartData" :options="trendsChartOptions" />
          </div>
        </div>

        <!-- Status + Orçamentos -->
        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">OS por Status</h2>
            <div style="height: 260px">
              <Doughnut :data="statusChartData" :options="doughnutOptions" />
            </div>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Orçamentos</h2>
            <div style="height: 260px">
              <Doughnut :data="quoteChartData" :options="doughnutOptions" />
            </div>
          </div>
        </div>

        <!-- Carga por Técnico -->
        <div v-if="overviewData.technician_workload.length > 0" class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Carga por Técnico</h2>
          <div :style="{ height: Math.max(140, overviewData.technician_workload.length * 40) + 'px' }">
            <Bar :data="workloadChartData" :options="workloadChartOptions" />
          </div>
        </div>
      </template>
    </template>

    <!-- ======================== RELATÓRIO DE O.S. ======================== -->
    <template v-else-if="activeTab === 'service_orders'">
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
            @click="loadServiceOrderReport(true)"
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

        <div v-if="serviceOrderReport.data.length === 0" class="rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-stone-400">
          Nenhuma O.S. encontrada para os filtros atuais.
        </div>

        <template v-else>
          <div class="t-wrap">
            <table class="w-full text-sm">
              <thead>
                <tr class="t-head">
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
              <tbody class="t-body">
                <tr v-for="row in sortedSoRows" :key="row.id" class="t-row">
                  <td class="px-4 py-3 font-mono text-xs text-amber-300">{{ row.order_number ?? row.reference }}</td>
                  <td class="px-4 py-3 td-main">{{ row.customer_name ?? '—' }}</td>
                  <td class="px-4 py-3 td-data">{{ row.equipment_summary || '—' }}</td>
                  <td class="px-4 py-3 td-data">{{ statusLabels[row.status] ?? row.status }}</td>
                  <td class="px-4 py-3 td-meta">
                    {{ warrantyDecisionLabels[row.warranty_decision_status ?? ''] ?? quoteDecisionLabels[row.latest_quote_status ?? ''] ?? '—' }}
                  </td>
                  <td class="px-4 py-3 td-data">{{ row.assigned_membership_name ?? '—' }}</td>
                  <td class="px-4 py-3 td-meta">{{ formatDate(row.opened_at) }}</td>
                  <td class="px-4 py-3 text-right">
                    <RouterLink :to="tenantRoute('service-orders.detail', { params: { id: row.id } })" class="text-xs text-amber-300 hover:text-amber-200">
                      Abrir
                    </RouterLink>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <AppPagination
            :meta="soMeta"
            @update:page="soPage = $event; loadServiceOrderReport()"
            @update:per-page="soPerPage = $event; loadServiceOrderReport(true)"
          />
        </template>
      </div>
    </template>

    <!-- ======================== RELATÓRIO FINANCEIRO ======================== -->
    <template v-else-if="activeTab === 'financial'">
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
            @click="loadFinancialReport(true)"
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

        <template v-else>
          <div class="t-wrap">
            <table class="w-full text-sm">
              <thead>
                <tr class="t-head">
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
              <tbody class="t-body">
                <tr v-for="row in sortedFinRows" :key="row.service_order_id" class="t-row">
                  <td class="px-4 py-3 font-mono text-xs text-amber-300">{{ row.order_number ?? row.reference }}</td>
                  <td class="px-4 py-3 td-main">{{ row.customer_name ?? '—' }}</td>
                  <td class="px-4 py-3 td-data">{{ statusLabels[row.status] ?? row.status }}</td>
                  <td class="px-4 py-3 td-data">{{ formatMoney(row.total_charged_cents) }}</td>
                  <td class="px-4 py-3 text-emerald-400">{{ formatMoney(row.total_paid_cents) }}</td>
                  <td class="px-4 py-3 text-amber-300">{{ formatMoney(row.outstanding_balance_cents) }}</td>
                  <td class="px-4 py-3 td-meta">{{ formatDate(row.last_payment_at) }}</td>
                  <td class="px-4 py-3 text-right">
                    <RouterLink :to="tenantRoute('service-orders.detail', { params: { id: row.service_order_id } })" class="text-xs text-amber-300 hover:text-amber-200">
                      Abrir
                    </RouterLink>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <AppPagination
            :meta="finMeta"
            @update:page="finPage = $event; loadFinancialReport()"
            @update:per-page="finPerPage = $event; loadFinancialReport(true)"
          />
        </template>
      </div>
    </template>

    <!-- ======================== POR TÉCNICO ======================== -->
    <template v-else-if="activeTab === 'technician'">
      <div class="grid gap-4 rounded-2xl border border-white/10 bg-white/5 p-6 lg:grid-cols-4">
        <label class="space-y-1">
          <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Abertas de</span>
          <input
            v-model="technicianFilters.opened_from"
            type="date"
            class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
          />
        </label>
        <label class="space-y-1">
          <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Abertas até</span>
          <input
            v-model="technicianFilters.opened_to"
            type="date"
            class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/40"
          />
        </label>
        <div class="flex flex-wrap items-end gap-2 lg:col-span-2">
          <button
            class="rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
            :disabled="technicianLoading"
            @click="loadTechnicianReport"
          >
            {{ technicianLoading ? 'Consultando…' : 'Aplicar filtros' }}
          </button>
          <button
            class="rounded-xl border border-white/10 px-4 py-2.5 text-sm text-stone-300 transition hover:border-white/20 hover:text-white"
            :disabled="technicianLoading"
            @click="technicianFilters = { opened_from: '', opened_to: '' }; loadTechnicianReport()"
          >
            Limpar filtros
          </button>
        </div>
      </div>

      <div v-if="technicianLoading" class="py-8 text-center text-sm text-stone-500">
        Carregando…
      </div>

      <template v-else-if="technicianRows.length > 0">
        <div class="t-wrap">
          <table class="w-full text-sm">
            <thead>
              <tr class="t-head">
                <ThSort :state="techState('name')" @click="techToggle('name')">Técnico</ThSort>
                <ThSort :state="techState('total')" @click="techToggle('total')">Total</ThSort>
                <ThSort :state="techState('in_progress')" @click="techToggle('in_progress')">Em Andamento</ThSort>
                <ThSort :state="techState('completed')" @click="techToggle('completed')">Concluídas</ThSort>
                <ThSort :state="techState('cancelled')" @click="techToggle('cancelled')">Canceladas</ThSort>
                <ThSort :state="techState('no_repair')" @click="techToggle('no_repair')">Sem Reparo</ThSort>
                <ThSort :state="techState('pending')" @click="techToggle('pending')">Pendentes</ThSort>
                <ThSort :state="techState('total_received_cents')" @click="techToggle('total_received_cents')">Receita</ThSort>
              </tr>
            </thead>
            <tbody class="t-body">
              <tr v-for="row in sortedTechRows" :key="row.id ?? 'unassigned'" class="t-row">
                <td class="px-4 py-3 td-main">{{ row.name }}</td>
                <td class="px-4 py-3 td-data font-semibold text-white">{{ row.total }}</td>
                <td class="px-4 py-3 td-data">{{ row.in_progress }}</td>
                <td class="px-4 py-3 text-emerald-400">{{ row.completed }}</td>
                <td class="px-4 py-3 text-red-400">{{ row.cancelled }}</td>
                <td class="px-4 py-3 td-meta">{{ row.no_repair }}</td>
                <td class="px-4 py-3 text-amber-300">{{ row.pending }}</td>
                <td class="px-4 py-3 text-emerald-400 font-semibold">{{ formatMoney(row.total_received_cents) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t border-white/10 text-xs font-semibold text-stone-400">
                <td class="px-4 py-3">Totais</td>
                <td class="px-4 py-3 text-white">{{ technicianTotals.total }}</td>
                <td class="px-4 py-3">{{ technicianTotals.in_progress }}</td>
                <td class="px-4 py-3 text-emerald-400">{{ technicianTotals.completed }}</td>
                <td class="px-4 py-3 text-red-400">{{ technicianTotals.cancelled }}</td>
                <td class="px-4 py-3">{{ technicianTotals.no_repair }}</td>
                <td class="px-4 py-3 text-amber-300">{{ technicianTotals.pending }}</td>
                <td class="px-4 py-3 text-emerald-400">{{ formatMoney(technicianTotals.total_received_cents) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </template>

      <div v-else-if="!technicianLoading" class="rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-stone-400">
        Nenhum dado de técnico encontrado para os filtros atuais.
      </div>
    </template>
  </div>
</template>

<style scoped>
@reference "../../style.css";
</style>
