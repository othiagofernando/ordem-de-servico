<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ApiError } from '../../api/client'
import { useSortable } from '../../composables/useSortable'
import AppPagination from '../../components/AppPagination.vue'
import {
  activateAdminCompany,
  extendAdminCompanySubscription,
  listAdminPlans,
  listAdminSubscriptions,
  suspendAdminCompany,
  type AdminPlan,
  type AdminSubscriptionListEntry,
  type AdminSubscriptionSummary,
} from '../../api/admin'
import { documentMask } from '../../utils/masks'

const subscriptions = ref<AdminSubscriptionListEntry[]>([])
const summary = ref<AdminSubscriptionSummary | null>(null)
const plans = ref<AdminPlan[]>([])
const loading = ref(true)
const error = ref('')
const notice = ref('')
const search = ref('')
const status = ref('')
const planId = ref('')
const expiringOnly = ref(false)
const route = useRoute()
const router = useRouter()
const actionKey = ref('')

const activePlan = computed(() => plans.value.find((plan) => String(plan.id) === planId.value) ?? null)

const page = ref(1)
const perPage = ref(15)

const { sorted: sortedSubscriptions, toggleSort, sortState } = useSortable(subscriptions, {
  company_name: (e) => e.company.name,
  plan_name: (e) => e.subscription?.plan.name,
  status: (e) => e.subscription?.status,
  expires_at: (e) => e.subscription?.expires_at,
  days_until_expiration: (e) => e.subscription?.days_until_expiration,
})

const paginationMeta = computed(() => {
  const total = sortedSubscriptions.value.length
  const lastPage = Math.ceil(total / perPage.value) || 1
  const from = (page.value - 1) * perPage.value + 1
  const to = Math.min(page.value * perPage.value, total)
  return { total, per_page: perPage.value, current_page: page.value, last_page: lastPage, from: total === 0 ? 0 : from, to }
})

const paginatedSubscriptions = computed(() => {
  const start = (page.value - 1) * perPage.value
  return sortedSubscriptions.value.slice(start, start + perPage.value)
})

const SORT_LABELS: Record<string, string> = {
  company_name: 'Empresa',
  plan_name: 'Plano',
  status: 'Status',
  expires_at: 'Vencimento',
  days_until_expiration: 'Dias restantes',
}

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
    const [plansResponse, subscriptionsResponse] = await Promise.all([
      listAdminPlans(),
      listAdminSubscriptions({
        query: search.value,
        status: status.value || undefined,
        plan_id: planId.value ? Number(planId.value) : undefined,
        expiring: expiringOnly.value,
      }),
    ])

    plans.value = plansResponse.data
    subscriptions.value = subscriptionsResponse.data
    summary.value = subscriptionsResponse.summary
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao carregar assinaturas globais.'
  } finally {
    loading.value = false
  }
}

function isRunning(key: string) {
  return actionKey.value === key
}

async function renewCompany(companyId: number) {
  actionKey.value = `renew:${companyId}`
  error.value = ''
  notice.value = ''

  try {
    await extendAdminCompanySubscription(companyId, { months: 1 })
    notice.value = 'Assinatura renovada em 30 dias.'
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao renovar assinatura.'
  } finally {
    actionKey.value = ''
  }
}

async function toggleCompanyStatus(companyId: number, nextStatus: 'activate' | 'suspend') {
  actionKey.value = `${nextStatus}:${companyId}`
  error.value = ''
  notice.value = ''

  try {
    if (nextStatus === 'activate') {
      await activateAdminCompany(companyId)
      notice.value = 'Empresa reativada com sucesso.'
    } else {
      await suspendAdminCompany(companyId)
      notice.value = 'Empresa suspensa com sucesso.'
    }

    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao atualizar status da empresa.'
  } finally {
    actionKey.value = ''
  }
}

async function submitFilters() {
  await router.push({
    name: 'admin.subscriptions',
    query: {
      ...(search.value.trim() ? { q: search.value.trim() } : {}),
      ...(status.value.trim() ? { status: status.value.trim() } : {}),
      ...(planId.value ? { plan_id: planId.value } : {}),
      ...(expiringOnly.value ? { expiring: '1' } : {}),
    },
  })
}

onMounted(async () => {
  if (typeof route.query.q === 'string') search.value = route.query.q
  if (typeof route.query.status === 'string') status.value = route.query.status
  if (typeof route.query.plan_id === 'string') planId.value = route.query.plan_id
  expiringOnly.value = route.query.expiring === '1'
  await load()
})

watch(
  () => [route.query.q, route.query.status, route.query.plan_id, route.query.expiring],
  async ([nextQ, nextStatus, nextPlanId, nextExpiring], [previousQ, previousStatus, previousPlanId, previousExpiring]) => {
    if (nextQ === previousQ && nextStatus === previousStatus && nextPlanId === previousPlanId && nextExpiring === previousExpiring) return
    search.value = typeof nextQ === 'string' ? nextQ : ''
    status.value = typeof nextStatus === 'string' ? nextStatus : ''
    planId.value = typeof nextPlanId === 'string' ? nextPlanId : ''
    expiringOnly.value = nextExpiring === '1'
    await load()
  },
)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold text-white">Assinaturas</h1>
      <p class="mt-1 text-sm text-stone-400">
        Acompanhamento global de planos, vencimentos e cobrança das empresas pelo super-admin.
      </p>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <div v-if="notice" class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
      {{ notice }}
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
      <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Total</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary?.total ?? 0 }}</p>
      </div>
      <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-300">Ativas</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary?.active ?? 0 }}</p>
      </div>
      <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">Suspensas</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary?.suspended ?? 0 }}</p>
      </div>
      <div class="rounded-2xl border border-rose-500/20 bg-rose-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-rose-300">Canceladas</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary?.cancelled ?? 0 }}</p>
      </div>
      <div class="rounded-2xl border border-violet-500/20 bg-violet-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-violet-300">Vencendo 15d</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary?.expiring_next_15_days ?? 0 }}</p>
      </div>
    </div>

    <form class="grid gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 lg:grid-cols-[1.4fr_220px_240px_auto_auto]" @submit.prevent="submitFilters">
      <input
        v-model="search"
        type="search"
        placeholder="Buscar por empresa, CNPJ ou e-mail"
        class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white outline-none placeholder:text-stone-500"
      />
      <select
        v-model="status"
        class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white outline-none"
      >
        <option value="">Todos os status</option>
        <option value="active">Ativa</option>
        <option value="suspended">Suspensa</option>
        <option value="cancelled">Cancelada</option>
      </select>
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
        <input v-model="expiringOnly" type="checkbox" class="rounded border-white/20 bg-transparent" />
        Vencendo em 15 dias
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

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando assinaturas…</div>

    <div v-else-if="subscriptions.length === 0" class="rounded-2xl border border-white/10 bg-white/5 px-6 py-12 text-center text-stone-500">
      Nenhuma assinatura encontrada para os filtros atuais.
    </div>

    <template v-else>
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Ordenar por</span>
        <button
          v-for="(label, key) in SORT_LABELS"
          :key="key"
          type="button"
          :class="[
            'flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs font-medium transition',
            sortState(key)
              ? 'border-amber-500/40 bg-amber-500/10 text-amber-300'
              : 'border-white/10 bg-white/5 text-stone-400 hover:border-white/20 hover:text-stone-200',
          ]"
          @click="toggleSort(key); page = 1"
        >
          {{ label }}
          <svg v-if="sortState(key) === 'asc'" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" class="h-3 w-3"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12V4M4 8l4-4 4 4"/></svg>
          <svg v-else-if="sortState(key) === 'desc'" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" class="h-3 w-3"><path stroke-linecap="round" stroke-linejoin="round" d="M8 4v8m-4-4l4 4 4-4"/></svg>
        </button>
      </div>

      <div class="space-y-3">
        <article
          v-for="entry in paginatedSubscriptions"
        :key="entry.subscription?.id ?? `${entry.company.id}-missing`"
        class="rounded-2xl border border-white/10 bg-white/5 p-5"
      >
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-3">
              <h2 class="text-lg font-semibold text-white">{{ entry.company.name ?? 'Empresa sem nome' }}</h2>
              <span class="rounded-full border border-white/10 px-2.5 py-1 text-xs font-semibold text-stone-300">
                {{ entry.subscription?.status ?? 'sem assinatura' }}
              </span>
              <span class="rounded-full border border-white/10 px-2.5 py-1 text-xs font-semibold text-stone-400">
                {{ entry.subscription?.plan.name ?? 'Sem plano' }}
              </span>
            </div>
            <p class="mt-2 text-sm text-stone-400">
              {{ entry.company.document ? documentMask(entry.company.document) : (entry.company.email || 'Sem identificador') }}
            </p>
            <p class="mt-1 text-xs text-stone-500">
              Vence em {{ fmtDate(entry.subscription?.expires_at) }}
              <span v-if="entry.subscription" class="mx-2 text-stone-600">•</span>
              <span v-if="entry.subscription" class="text-stone-400">
                {{ entry.subscription.days_until_expiration }} dia(s)
              </span>
            </p>
          </div>

        <div class="flex flex-wrap items-center gap-2">
          <RouterLink
            :to="{ name: 'admin.subscriptions.edit', params: { id: entry.company.id } }"
            class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-300 transition hover:bg-amber-500/20"
          >
            Editar assinatura
          </RouterLink>
          <RouterLink
            :to="{ name: 'admin.company', params: { id: entry.company.id } }"
              class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
            >
              Detalhes
            </RouterLink>
          <RouterLink
            :to="{ name: 'admin.company.audit', params: { id: entry.company.id } }"
            class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
          >
            Histórico
          </RouterLink>
          <RouterLink
            v-if="entry.subscription?.plan.id"
            :to="{ name: 'admin.billing', query: { plan_id: String(entry.subscription.plan.id) } }"
            class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-300 transition hover:bg-emerald-500/20"
          >
            Cobranças do plano
          </RouterLink>
          <button
            v-if="entry.company.id"
            type="button"
            class="rounded-xl border border-violet-500/20 bg-violet-500/10 px-3 py-2 text-xs font-semibold text-violet-300 transition hover:bg-violet-500/20 disabled:opacity-60"
            :disabled="isRunning(`renew:${entry.company.id}`)"
            @click="renewCompany(entry.company.id)"
          >
            {{ isRunning(`renew:${entry.company.id}`) ? 'Renovando…' : 'Renovar 30 dias' }}
          </button>
          <button
            v-if="entry.company.id && entry.company.status === 'active'"
            type="button"
            class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-300 transition hover:bg-amber-500/20 disabled:opacity-60"
            :disabled="isRunning(`suspend:${entry.company.id}`)"
            @click="toggleCompanyStatus(entry.company.id, 'suspend')"
          >
            {{ isRunning(`suspend:${entry.company.id}`) ? 'Suspendendo…' : 'Suspender' }}
          </button>
          <button
            v-else-if="entry.company.id && entry.company.status === 'suspended'"
            type="button"
            class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-300 transition hover:bg-emerald-500/20 disabled:opacity-60"
            :disabled="isRunning(`activate:${entry.company.id}`)"
            @click="toggleCompanyStatus(entry.company.id, 'activate')"
          >
            {{ isRunning(`activate:${entry.company.id}`) ? 'Reativando…' : 'Reativar' }}
          </button>
        </div>
      </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-4">
          <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
            <p class="text-xs uppercase tracking-widest text-stone-500">Usuários ativos</p>
            <p class="mt-2 text-xl font-semibold text-white">{{ entry.subscription?.usage.active_users ?? 0 }}</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
            <p class="text-xs uppercase tracking-widest text-stone-500">OS no mês</p>
            <p class="mt-2 text-xl font-semibold text-white">{{ entry.subscription?.usage.service_orders_this_month ?? 0 }}</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
            <p class="text-xs uppercase tracking-widest text-stone-500">Armazenamento</p>
            <p class="mt-2 text-xl font-semibold text-white">{{ entry.subscription?.usage.attachments_storage_mb ?? 0 }} MB</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
            <p class="text-xs uppercase tracking-widest text-stone-500">Última fatura</p>
            <p class="mt-2 text-sm font-semibold text-white">
              {{ entry.latest_invoice ? fmtMoney(entry.latest_invoice.amount_cents, entry.latest_invoice.currency) : '—' }}
            </p>
            <p class="mt-1 text-xs text-stone-500">
              {{ entry.latest_invoice?.status ?? 'Sem fatura' }}
            </p>
          </div>
        </div>
        </article>
      </div>

      <AppPagination
        :meta="paginationMeta"
        @update:page="(p) => { page = p }"
        @update:perPage="(n) => { perPage = n; page = 1 }"
      />
    </template>
  </div>
</template>
