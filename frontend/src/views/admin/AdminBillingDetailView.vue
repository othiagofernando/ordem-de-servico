<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { ApiError } from '../../api/client'
import { getAdminBillingInvoice, type AdminBillingInvoiceListEntry } from '../../api/admin'
import { documentMask } from '../../utils/masks'

const route = useRoute()
const loading = ref(true)
const error = ref('')
const invoice = ref<AdminBillingInvoiceListEntry | null>(null)

const payloadJson = computed(() => {
  if (!invoice.value?.payload) return ''
  return JSON.stringify(invoice.value.payload, null, 2)
})

const INVOICE_STATUS_LABELS: Record<string, string> = {
  pending: 'Pendente',
  confirmed: 'Confirmado',
  received: 'Recebido',
  received_in_cash: 'Recebido em dinheiro',
  overdue: 'Vencida',
  refunded: 'Reembolsado',
  refund_requested: 'Solicitação de reembolso',
  refund_in_progress: 'Reembolso em andamento',
  cancelled: 'Cancelado',
  chargeback_requested: 'Chargeback solicitado',
  chargeback_dispute: 'Disputa de chargeback',
  awaiting_chargeback_reversal: 'Aguardando reversão de chargeback',
  dunning_requested: 'Em negativação',
  dunning_received: 'Recuperado (negativação)',
  awaiting_risk_analysis: 'Aguardando análise de risco',
}

const INVOICE_STATUS_CLASSES: Record<string, string> = {
  pending: 'border-amber-500/30 text-amber-300',
  confirmed: 'border-emerald-500/30 text-emerald-300',
  received: 'border-emerald-500/30 text-emerald-300',
  received_in_cash: 'border-emerald-500/30 text-emerald-300',
  dunning_received: 'border-emerald-500/30 text-emerald-300',
  overdue: 'border-red-500/30 text-red-300',
  dunning_requested: 'border-red-500/30 text-red-300',
  refunded: 'border-sky-500/30 text-sky-300',
  refund_requested: 'border-sky-500/30 text-sky-300',
  refund_in_progress: 'border-sky-500/30 text-sky-300',
  chargeback_requested: 'border-orange-500/30 text-orange-300',
  chargeback_dispute: 'border-orange-500/30 text-orange-300',
  awaiting_chargeback_reversal: 'border-orange-500/30 text-orange-300',
  awaiting_risk_analysis: 'border-amber-500/30 text-amber-300',
  cancelled: 'border-stone-500/20 text-stone-400',
}

const COMPANY_STATUS_LABELS: Record<string, string> = {
  active: 'Ativa',
  suspended: 'Suspensa',
  cancelled: 'Cancelada',
  pending: 'Pendente',
}

const SUBSCRIPTION_STATUS_LABELS: Record<string, string> = {
  active: 'Ativa',
  suspended: 'Suspensa',
  cancelled: 'Cancelada',
}

function invoiceStatusLabel(s: string | null | undefined): string {
  if (!s) return '—'
  return INVOICE_STATUS_LABELS[s.toLowerCase()] ?? s
}

function invoiceStatusClass(s: string | null | undefined): string {
  return INVOICE_STATUS_CLASSES[(s ?? '').toLowerCase()] ?? 'border-white/10 text-stone-300'
}

function companyStatusLabel(s: string | null | undefined): string {
  if (!s) return '—'
  return COMPANY_STATUS_LABELS[s.toLowerCase()] ?? s
}

function subscriptionStatusLabel(s: string | null | undefined): string {
  if (!s) return '—'
  return SUBSCRIPTION_STATUS_LABELS[s.toLowerCase()] ?? s
}

function fmtDate(value: string | null | undefined) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(new Date(value))
}

function fmtDateTime(value: string | null | undefined) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function fmtMoney(cents: number, currency = 'BRL') {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency })
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    invoice.value = (await getAdminBillingInvoice(Number(route.params.id))).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao carregar cobrança.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <RouterLink :to="{ name: 'admin.billing' }" class="text-sm text-stone-500 hover:text-stone-300">← Cobranças</RouterLink>
      <RouterLink
        v-if="invoice?.company.id"
        :to="{ name: 'admin.company', params: { id: invoice.company.id } }"
        class="text-sm text-stone-500 hover:text-stone-300"
      >
        Ver empresa
      </RouterLink>
    </div>

    <div>
      <h1 class="text-2xl font-semibold text-white">Detalhes da Cobrança</h1>
      <p class="mt-1 text-sm text-stone-400">
        Inspeção completa da fatura, vínculo com empresa e retorno bruto do gateway quando disponível.
      </p>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando cobrança…</div>

    <template v-else-if="invoice">
      <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-3">
              <h2 class="text-xl font-semibold text-white">{{ invoice.company.name ?? 'Empresa sem nome' }}</h2>
              <span :class="invoiceStatusClass(invoice.status)" class="rounded-full border px-2.5 py-1 text-xs font-semibold">
                {{ invoiceStatusLabel(invoice.status) }}
              </span>
              <span class="rounded-full border border-white/10 px-2.5 py-1 text-xs font-semibold text-stone-400">
                {{ invoice.subscription.plan.name ?? 'Sem plano' }}
              </span>
            </div>
            <p class="mt-2 text-sm text-stone-400">
              {{ invoice.company.document ? documentMask(invoice.company.document) : (invoice.company.email || 'Sem identificador') }}
            </p>
          </div>

          <div class="flex flex-wrap gap-2">
            <a
              v-if="invoice.invoice_url"
              :href="invoice.invoice_url"
              target="_blank"
              rel="noreferrer"
              class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-300 transition hover:bg-amber-500/20"
            >
              Abrir cobrança
            </a>
            <a
              v-if="invoice.pdf_url"
              :href="invoice.pdf_url"
              target="_blank"
              rel="noreferrer"
              class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-300 transition hover:bg-emerald-500/20"
            >
              Abrir comprovante
            </a>
          </div>
        </div>
      </section>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Valor</p>
          <p class="mt-2 text-2xl font-bold text-white">{{ fmtMoney(invoice.amount_cents, invoice.currency) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Vencimento</p>
          <p class="mt-2 text-sm font-semibold text-white">{{ fmtDate(invoice.due_at) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Pagamento</p>
          <p class="mt-2 text-sm font-semibold text-white">{{ fmtDate(invoice.paid_at) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Gateway ID</p>
          <p class="mt-2 text-sm font-semibold text-white break-all">{{ invoice.gateway_invoice_id || '—' }}</p>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
        <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
          <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Relacionamentos</h2>
          <dl class="mt-5 grid gap-4 md:grid-cols-2">
            <div>
              <dt class="text-xs uppercase tracking-widest text-stone-500">Empresa</dt>
              <dd class="mt-1 text-sm text-stone-200">{{ invoice.company.name ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-xs uppercase tracking-widest text-stone-500">Status da empresa</dt>
              <dd class="mt-1 text-sm text-stone-200">{{ companyStatusLabel(invoice.company.status) }}</dd>
            </div>
            <div>
              <dt class="text-xs uppercase tracking-widest text-stone-500">Assinatura</dt>
              <dd class="mt-1 text-sm text-stone-200">{{ subscriptionStatusLabel(invoice.subscription.status) }}</dd>
            </div>
            <div>
              <dt class="text-xs uppercase tracking-widest text-stone-500">Plano</dt>
              <dd class="mt-1 text-sm text-stone-200">{{ invoice.subscription.plan.name ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-xs uppercase tracking-widest text-stone-500">Criada em</dt>
              <dd class="mt-1 text-sm text-stone-200">{{ fmtDateTime(invoice.created_at) }}</dd>
            </div>
            <div>
              <dt class="text-xs uppercase tracking-widest text-stone-500">Atualizada em</dt>
              <dd class="mt-1 text-sm text-stone-200">{{ fmtDateTime(invoice.updated_at) }}</dd>
            </div>
          </dl>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
          <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Payload do gateway</h2>
          <div v-if="payloadJson" class="mt-5 rounded-2xl border border-white/10 bg-black/20 p-4">
            <pre class="overflow-x-auto text-xs text-stone-300">{{ payloadJson }}</pre>
          </div>
          <p v-else class="mt-5 text-sm text-stone-500">Nenhum payload bruto armazenado para esta cobrança.</p>
        </section>
      </div>
    </template>
  </div>
</template>
