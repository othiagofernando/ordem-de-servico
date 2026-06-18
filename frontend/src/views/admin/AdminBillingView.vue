<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ApiError } from '../../api/client'
import {
  listAdminBillingInvoices,
  listAdminPlans,
  type AdminBillingInvoiceListEntry,
  type AdminBillingInvoiceSummary,
  type AdminPlan,
} from '../../api/admin'

const invoices = ref<AdminBillingInvoiceListEntry[]>([])
const summary = ref<AdminBillingInvoiceSummary | null>(null)
const plans = ref<AdminPlan[]>([])
const loading = ref(true)
const error = ref('')
const search = ref('')
const status = ref('')
const planId = ref('')
const overdueOnly = ref(false)
const route = useRoute()
const router = useRouter()

const activePlan = computed(() => plans.value.find((plan) => String(plan.id) === planId.value) ?? null)

function fmtDate(value: string | null | undefined) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(new Date(value))
}

function fmtMoney(cents: number, currency = 'BRL') {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency })
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const [plansResponse, invoicesResponse] = await Promise.all([
      listAdminPlans(),
      listAdminBillingInvoices({
        query: search.value,
        status: status.value || undefined,
        plan_id: planId.value ? Number(planId.value) : undefined,
        overdue: overdueOnly.value,
      }),
    ])

    plans.value = plansResponse.data
    invoices.value = invoicesResponse.data
    summary.value = invoicesResponse.summary
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao carregar cobranças globais.'
  } finally {
    loading.value = false
  }
}

async function submitFilters() {
  await router.push({
    name: 'admin.billing',
    query: {
      ...(search.value.trim() ? { q: search.value.trim() } : {}),
      ...(status.value.trim() ? { status: status.value.trim() } : {}),
      ...(planId.value ? { plan_id: planId.value } : {}),
      ...(overdueOnly.value ? { overdue: '1' } : {}),
    },
  })
}

onMounted(async () => {
  if (typeof route.query.q === 'string') search.value = route.query.q
  if (typeof route.query.status === 'string') status.value = route.query.status
  if (typeof route.query.plan_id === 'string') planId.value = route.query.plan_id
  overdueOnly.value = route.query.overdue === '1'
  await load()
})

watch(
  () => [route.query.q, route.query.status, route.query.plan_id, route.query.overdue],
  async ([nextQ, nextStatus, nextPlanId, nextOverdue], [previousQ, previousStatus, previousPlanId, previousOverdue]) => {
    if (nextQ === previousQ && nextStatus === previousStatus && nextPlanId === previousPlanId && nextOverdue === previousOverdue) return
    search.value = typeof nextQ === 'string' ? nextQ : ''
    status.value = typeof nextStatus === 'string' ? nextStatus : ''
    planId.value = typeof nextPlanId === 'string' ? nextPlanId : ''
    overdueOnly.value = nextOverdue === '1'
    await load()
  },
)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold text-white">Cobranças</h1>
      <p class="mt-1 text-sm text-stone-400">
        Visão global de faturas, pendências e comprovantes para acompanhamento operacional do super-admin.
      </p>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Total</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary?.total ?? 0 }}</p>
      </div>
      <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">Pendentes</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary?.pending ?? 0 }}</p>
      </div>
      <div class="rounded-2xl border border-rose-500/20 bg-rose-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-rose-300">Atrasadas</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary?.overdue ?? 0 }}</p>
      </div>
      <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-300">Pagas</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary?.paid ?? 0 }}</p>
      </div>
    </div>

    <form class="grid gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 lg:grid-cols-[1.4fr_220px_240px_auto_auto]" @submit.prevent="submitFilters">
      <input
        v-model="search"
        type="search"
        placeholder="Buscar por empresa, CNPJ ou e-mail"
        class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white outline-none placeholder:text-stone-500"
      />
      <input
        v-model="status"
        type="text"
        placeholder="Status da fatura"
        class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white outline-none placeholder:text-stone-500"
      />
      <select
        v-model="planId"
        class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white outline-none"
      >
        <option value="">Todos os planos</option>
        <option v-for="plan in plans" :key="plan.id" :value="String(plan.id)">
          {{ plan.name }}
        </option>
      </select>
      <label class="flex items-center gap-2 rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-stone-300">
        <input v-model="overdueOnly" type="checkbox" class="rounded border-white/20 bg-transparent" />
        Apenas atrasadas
      </label>
      <button type="submit" class="rounded-2xl bg-amber-500 px-4 py-3 text-sm font-semibold text-stone-950 hover:bg-amber-400">
        Filtrar
      </button>
    </form>

    <div
      v-if="activePlan"
      class="rounded-2xl border border-sky-500/20 bg-sky-500/10 px-4 py-4 text-sm text-sky-100"
    >
      Filtro por plano ativo: <strong>{{ activePlan.name }}</strong>.
      <RouterLink :to="{ name: 'admin.plans' }" class="ml-2 text-sky-300 hover:text-sky-200">Voltar para Planos</RouterLink>
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando cobranças…</div>

    <div v-else-if="invoices.length === 0" class="rounded-2xl border border-white/10 bg-white/5 px-6 py-12 text-center text-stone-500">
      Nenhuma cobrança encontrada para os filtros atuais.
    </div>

    <div v-else class="space-y-3">
      <article
        v-for="invoice in invoices"
        :key="invoice.id"
        class="rounded-2xl border border-white/10 bg-white/5 p-5"
      >
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-3">
              <h2 class="text-lg font-semibold text-white">{{ invoice.company.name ?? 'Empresa sem nome' }}</h2>
              <span class="rounded-full border border-white/10 px-2.5 py-1 text-xs font-semibold text-stone-300">
                {{ invoice.status }}
              </span>
              <span class="rounded-full border border-white/10 px-2.5 py-1 text-xs font-semibold text-stone-400">
                {{ invoice.subscription.plan.name ?? 'Sem plano' }}
              </span>
            </div>
            <p class="mt-2 text-sm text-stone-400">
              {{ invoice.company.document || invoice.company.email || 'Sem identificador' }}
            </p>
            <p class="mt-1 text-xs text-stone-500">
              Vencimento: {{ fmtDate(invoice.due_at) }}
              <span class="mx-2 text-stone-600">•</span>
              Pagamento: {{ fmtDate(invoice.paid_at) }}
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <RouterLink
              :to="{ name: 'admin.billing.show', params: { id: invoice.id } }"
              class="rounded-xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-300 transition hover:bg-sky-500/20"
            >
              Detalhes
            </RouterLink>
            <RouterLink
              :to="{ name: 'admin.company', params: { id: invoice.company.id } }"
              class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
            >
              Empresa
            </RouterLink>
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
              Comprovante
            </a>
          </div>
        </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-3">
          <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
            <p class="text-xs uppercase tracking-widest text-stone-500">Valor</p>
            <p class="mt-2 text-xl font-semibold text-white">{{ fmtMoney(invoice.amount_cents, invoice.currency) }}</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
            <p class="text-xs uppercase tracking-widest text-stone-500">Assinatura</p>
            <p class="mt-2 text-sm font-semibold text-white">{{ invoice.subscription.status ?? '—' }}</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
            <p class="text-xs uppercase tracking-widest text-stone-500">Plano</p>
            <p class="mt-2 text-sm font-semibold text-white">{{ invoice.subscription.plan.name ?? '—' }}</p>
          </div>
        </div>
      </article>
    </div>
  </div>
</template>
