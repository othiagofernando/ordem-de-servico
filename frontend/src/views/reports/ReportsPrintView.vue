<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import {
  getServiceOrderReportForPrint,
  getFinancialReportForPrint,
  getTechnicianReport,
  type ServiceOrderReportRow,
  type FinancialReportRow,
  type TechnicianReportRow,
} from '../../api/serviceOrders'

const route = useRoute()
const type = route.query.type as string
const companyName = (route.query.company as string) || 'Empresa'

const loading = ref(true)
const error = ref('')

const soRows = ref<ServiceOrderReportRow[]>([])
const finRows = ref<FinancialReportRow[]>([])
const finTotals = ref({ total_charged_cents: 0, total_paid_cents: 0, outstanding_balance_cents: 0 })
const techRows = ref<TechnicianReportRow[]>([])

const generatedAt = new Date().toLocaleString('pt-BR')

const reportTitle = computed(() => {
  if (type === 'financial') return 'Relatório Financeiro'
  if (type === 'technician') return 'Relatório por Técnico'
  return 'Relatório de Ordens de Serviço'
})

const activeFilters = computed<string[]>(() => {
  const q = route.query
  const labels: string[] = []
  if (q.q) labels.push(`Busca: ${q.q}`)
  if (q.queue) labels.push(`Fila: ${q.queue}`)
  if (q.opened_from) labels.push(`Abertas a partir de ${formatDate(q.opened_from as string)}`)
  if (q.opened_to) labels.push(`Abertas até ${formatDate(q.opened_to as string)}`)
  if (q.payment_method) labels.push(`Pagamento: ${q.payment_method}`)
  if (q.balance_state === 'outstanding') labels.push('Somente com saldo pendente')
  if (q.balance_state === 'settled') labels.push('Somente quitadas')
  if (q.received_from) labels.push(`Recebimentos a partir de ${formatDate(q.received_from as string)}`)
  if (q.received_to) labels.push(`Recebimentos até ${formatDate(q.received_to as string)}`)
  return labels
})

const techTotals = computed(() => ({
  total: techRows.value.reduce((s, r) => s + r.total, 0),
  in_progress: techRows.value.reduce((s, r) => s + r.in_progress, 0),
  completed: techRows.value.reduce((s, r) => s + r.completed, 0),
  cancelled: techRows.value.reduce((s, r) => s + r.cancelled, 0),
  no_repair: techRows.value.reduce((s, r) => s + r.no_repair, 0),
  pending: techRows.value.reduce((s, r) => s + r.pending, 0),
  total_received_cents: techRows.value.reduce((s, r) => s + r.total_received_cents, 0),
}))

const statusLabels: Record<string, string> = {
  draft: 'Rascunho', open: 'Aberta', in_progress: 'Em execução',
  completed: 'Concluída', ready_for_pickup: 'Pronta p/ retirada',
  delivered: 'Entregue', cancelled: 'Cancelada', no_repair: 'Sem reparo',
}

function formatDate(v: string | null) {
  if (!v) return '—'
  return new Date(v).toLocaleDateString('pt-BR')
}

function formatMoney(cents: number) {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function label(v: string | null, map: Record<string, string>) {
  return v ? (map[v] ?? v) : '—'
}

onMounted(async () => {
  try {
    const q = route.query
    if (type === 'financial') {
      const res = await getFinancialReportForPrint({
        q: q.q as string | undefined,
        payment_method: q.payment_method as string | undefined,
        balance_state: q.balance_state as 'settled' | 'outstanding' | undefined,
        received_from: q.received_from as string | undefined,
        received_to: q.received_to as string | undefined,
      })
      finRows.value = res.data
      finTotals.value = res.totals
    } else if (type === 'technician') {
      const res = await getTechnicianReport({
        opened_from: q.opened_from as string | undefined,
        opened_to: q.opened_to as string | undefined,
      })
      techRows.value = res.data
    } else {
      const res = await getServiceOrderReportForPrint({
        q: q.q as string | undefined,
        queue: q.queue as string | undefined,
        opened_from: q.opened_from as string | undefined,
        opened_to: q.opened_to as string | undefined,
      })
      soRows.value = res.data
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Erro ao carregar dados.'
  } finally {
    loading.value = false
    if (!error.value) {
      setTimeout(() => window.print(), 300)
    }
  }
})
</script>

<template>
  <div class="print-app">
    <!-- Toolbar — hidden when printing -->
    <div class="toolbar print-hide">
      <span class="toolbar-title">{{ reportTitle }}</span>
      <button class="btn-print" :disabled="loading" @click="window.print()">
        Imprimir / Salvar PDF
      </button>
    </div>

    <div v-if="loading" class="print-hide state-message">Carregando dados…</div>
    <div v-else-if="error" class="print-hide state-message error">{{ error }}</div>

    <div v-else class="page">
      <!-- Report header -->
      <header class="report-header">
        <div>
          <div class="company-name">{{ companyName }}</div>
          <h1 class="report-title">{{ reportTitle }}</h1>
        </div>
        <div class="report-meta">
          <div>Emitido em {{ generatedAt }}</div>
          <div v-if="activeFilters.length" class="filters-applied">
            <strong>Filtros:</strong> {{ activeFilters.join(' · ') }}
          </div>
        </div>
      </header>

      <!-- Service Orders table -->
      <template v-if="type === 'service_orders' || !type">
        <p class="row-count">{{ soRows.length }} {{ soRows.length === 1 ? 'registro' : 'registros' }}</p>
        <table>
          <thead>
            <tr>
              <th>Referência</th>
              <th>N.º OS</th>
              <th>Status</th>
              <th>Abertura</th>
              <th>Cliente</th>
              <th>Equipamento</th>
              <th>Técnico</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in soRows" :key="r.id">
              <td class="mono">{{ r.reference }}</td>
              <td class="mono">{{ r.order_number ?? '—' }}</td>
              <td>{{ label(r.status, statusLabels) }}</td>
              <td>{{ formatDate(r.opened_at) }}</td>
              <td>{{ r.customer_name ?? '—' }}</td>
              <td>{{ r.equipment_summary || '—' }}</td>
              <td>{{ r.assigned_membership_name ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </template>

      <!-- Financial table -->
      <template v-else-if="type === 'financial'">
        <p class="row-count">{{ finRows.length }} {{ finRows.length === 1 ? 'registro' : 'registros' }}</p>
        <table>
          <thead>
            <tr>
              <th>Referência</th>
              <th>N.º OS</th>
              <th>Status</th>
              <th>Cliente</th>
              <th>Técnico</th>
              <th class="num">Cobrado</th>
              <th class="num">Pago</th>
              <th class="num">Saldo</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in finRows" :key="r.service_order_id">
              <td class="mono">{{ r.reference }}</td>
              <td class="mono">{{ r.order_number ?? '—' }}</td>
              <td>{{ label(r.status, statusLabels) }}</td>
              <td>{{ r.customer_name ?? '—' }}</td>
              <td>{{ r.assigned_membership_name ?? '—' }}</td>
              <td class="num">{{ formatMoney(r.total_charged_cents) }}</td>
              <td class="num">{{ formatMoney(r.total_paid_cents) }}</td>
              <td class="num" :class="r.outstanding_balance_cents > 0 ? 'outstanding' : ''">
                {{ formatMoney(r.outstanding_balance_cents) }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="totals-row">
              <td colspan="5"><strong>Total</strong></td>
              <td class="num"><strong>{{ formatMoney(finTotals.total_charged_cents) }}</strong></td>
              <td class="num"><strong>{{ formatMoney(finTotals.total_paid_cents) }}</strong></td>
              <td class="num outstanding"><strong>{{ formatMoney(finTotals.outstanding_balance_cents) }}</strong></td>
            </tr>
          </tfoot>
        </table>
      </template>

      <!-- Technician table -->
      <template v-else-if="type === 'technician'">
        <p class="row-count">{{ techRows.length }} {{ techRows.length === 1 ? 'técnico' : 'técnicos' }}</p>
        <table>
          <thead>
            <tr>
              <th>Técnico</th>
              <th class="num">Total</th>
              <th class="num">Em Andamento</th>
              <th class="num">Concluídas</th>
              <th class="num">Canceladas</th>
              <th class="num">Sem Reparo</th>
              <th class="num">Pendentes</th>
              <th class="num">Receita</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in techRows" :key="r.id ?? r.name">
              <td>{{ r.name }}</td>
              <td class="num">{{ r.total }}</td>
              <td class="num">{{ r.in_progress }}</td>
              <td class="num">{{ r.completed }}</td>
              <td class="num">{{ r.cancelled }}</td>
              <td class="num">{{ r.no_repair }}</td>
              <td class="num">{{ r.pending }}</td>
              <td class="num">{{ formatMoney(r.total_received_cents) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="totals-row">
              <td><strong>Total</strong></td>
              <td class="num"><strong>{{ techTotals.total }}</strong></td>
              <td class="num"><strong>{{ techTotals.in_progress }}</strong></td>
              <td class="num"><strong>{{ techTotals.completed }}</strong></td>
              <td class="num"><strong>{{ techTotals.cancelled }}</strong></td>
              <td class="num"><strong>{{ techTotals.no_repair }}</strong></td>
              <td class="num"><strong>{{ techTotals.pending }}</strong></td>
              <td class="num"><strong>{{ formatMoney(techTotals.total_received_cents) }}</strong></td>
            </tr>
          </tfoot>
        </table>
      </template>

      <footer class="report-footer">
        TechFix OS · Relatório gerado automaticamente
      </footer>
    </div>
  </div>
</template>

<style scoped>
* { box-sizing: border-box; }

.print-app {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  font-size: 12px;
  color: #1a1a1a;
  background: #f0eeeb;
  min-height: 100vh;
}

/* Toolbar */
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 24px;
  background: #1c1917;
  gap: 12px;
}

.toolbar-title {
  font-size: 14px;
  font-weight: 600;
  color: #fbbf24;
}

.btn-print {
  background: #f59e0b;
  color: #1c1917;
  font-size: 13px;
  font-weight: 700;
  border: none;
  border-radius: 8px;
  padding: 8px 20px;
  cursor: pointer;
}

.btn-print:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* State messages */
.state-message {
  padding: 48px;
  text-align: center;
  color: #78716c;
  font-size: 14px;
}

.state-message.error { color: #b91c1c; }

/* Page */
.page {
  max-width: 210mm;
  margin: 24px auto;
  background: #ffffff;
  padding: 24px 32px;
  border-radius: 4px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.10);
}

/* Header */
.report-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 2px solid #1c1917;
}

.company-name {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #78716c;
  margin-bottom: 4px;
}

.report-title {
  font-size: 18px;
  font-weight: 700;
  margin: 0;
  color: #1c1917;
}

.report-meta {
  text-align: right;
  font-size: 11px;
  color: #78716c;
  line-height: 1.6;
}

.filters-applied {
  margin-top: 4px;
  font-size: 10px;
  max-width: 260px;
}

.row-count {
  font-size: 11px;
  color: #78716c;
  margin: 0 0 8px;
}

/* Table */
table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
}

thead th {
  background: #f5f3f0;
  text-align: left;
  padding: 6px 8px;
  font-weight: 600;
  color: #44403c;
  border-bottom: 1px solid #d6d3d1;
  white-space: nowrap;
}

thead th.num { text-align: right; }

tbody td {
  padding: 5px 8px;
  border-bottom: 1px solid #f0eeeb;
  color: #1c1917;
  vertical-align: top;
}

tbody tr:last-child td { border-bottom: none; }

tbody tr:nth-child(even) td { background: #faf9f7; }

td.num { text-align: right; white-space: nowrap; }
td.mono { font-variant-numeric: tabular-nums; }
td.outstanding { color: #b91c1c; }

/* Totals row */
tfoot .totals-row td {
  padding: 7px 8px;
  border-top: 2px solid #d6d3d1;
  background: #f5f3f0;
}

/* Footer */
.report-footer {
  margin-top: 24px;
  padding-top: 12px;
  border-top: 1px solid #e7e5e4;
  font-size: 10px;
  color: #a8a29e;
  text-align: center;
}

/* Print */
@media print {
  .print-hide { display: none !important; }

  .print-app { background: #fff; }

  .page {
    margin: 0;
    padding: 12mm 14mm;
    border-radius: 0;
    box-shadow: none;
    max-width: 100%;
  }

  table { page-break-inside: auto; }
  tr { page-break-inside: avoid; }

  .report-header { margin-bottom: 12px; padding-bottom: 10px; }
}
</style>
