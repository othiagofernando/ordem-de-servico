<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ApiError } from '../../api/client'
import {
  activateAdminCompany,
  extendAdminCompanySubscription,
  getAdminAlerts,
  type AdminAlertsPayload,
} from '../../api/admin'

const alerts = ref<AdminAlertsPayload | null>(null)
const loading = ref(true)
const error = ref('')
const notice = ref('')
const actionKey = ref('')
const route = useRoute()
const router = useRouter()
const focus = ref<'overdue' | 'expiring' | 'suspended' | 'admin' | ''>('')

const sections = computed(() => [
  { key: 'overdue', label: 'Cobranças atrasadas' },
  { key: 'expiring', label: 'Assinaturas vencendo' },
  { key: 'suspended', label: 'Empresas suspensas' },
  { key: 'admin', label: 'Eventos admin' },
])

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
    alerts.value = await getAdminAlerts()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao carregar alertas globais.'
  } finally {
    loading.value = false
  }
}

function isRunning(key: string) {
  return actionKey.value === key
}

function isVisible(section: 'overdue' | 'expiring' | 'suspended' | 'admin') {
  return !focus.value || focus.value === section
}

async function setFocus(nextFocus: 'overdue' | 'expiring' | 'suspended' | 'admin' | '') {
  focus.value = nextFocus
  await router.push({
    name: 'admin.alerts',
    query: nextFocus ? { focus: nextFocus } : {},
  })
}

async function renewCompany(companyId: number | null) {
  if (!companyId) return

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

async function reactivateCompany(companyId: number | null) {
  if (!companyId) return

  actionKey.value = `activate:${companyId}`
  error.value = ''
  notice.value = ''

  try {
    await activateAdminCompany(companyId)
    notice.value = 'Empresa reativada com sucesso.'
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao reativar empresa.'
  } finally {
    actionKey.value = ''
  }
}

onMounted(async () => {
  focus.value = typeof route.query.focus === 'string' ? (route.query.focus as typeof focus.value) : ''
  await load()
})

watch(
  () => route.query.focus,
  (nextFocus, previousFocus) => {
    if (nextFocus === previousFocus) return
    focus.value = typeof nextFocus === 'string' ? (nextFocus as typeof focus.value) : ''
  },
)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold text-white">Alertas</h1>
      <p class="mt-1 text-sm text-stone-400">
        Centro de atenção do super-admin com pendências financeiras, vencimentos próximos, empresas suspensas e eventos administrativos recentes.
      </p>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <div v-if="notice" class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
      {{ notice }}
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando alertas…</div>

    <template v-else-if="alerts">
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <button type="button" class="rounded-2xl border border-rose-500/20 bg-rose-500/5 p-5 text-left transition hover:bg-rose-500/10" @click="setFocus('overdue')">
          <p class="text-xs font-semibold uppercase tracking-widest text-rose-300">Cobranças atrasadas</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ alerts.summary.overdue_invoices }}</p>
        </button>
        <button type="button" class="rounded-2xl border border-violet-500/20 bg-violet-500/5 p-5 text-left transition hover:bg-violet-500/10" @click="setFocus('expiring')">
          <p class="text-xs font-semibold uppercase tracking-widest text-violet-300">Assinaturas vencendo</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ alerts.summary.expiring_subscriptions }}</p>
        </button>
        <button type="button" class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5 text-left transition hover:bg-amber-500/10" @click="setFocus('suspended')">
          <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">Empresas suspensas</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ alerts.summary.suspended_companies }}</p>
        </button>
        <button type="button" class="rounded-2xl border border-sky-500/20 bg-sky-500/5 p-5 text-left transition hover:bg-sky-500/10" @click="setFocus('admin')">
          <p class="text-xs font-semibold uppercase tracking-widest text-sky-300">Eventos admin</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ alerts.summary.recent_admin_events }}</p>
        </button>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
          :class="!focus ? 'border-amber-500/20 bg-amber-500/10 text-amber-300' : 'border-white/10 bg-white/5 text-stone-300 hover:bg-white/10'"
          @click="setFocus('')"
        >
          Todos
        </button>
        <button
          v-for="section in sections"
          :key="section.key"
          type="button"
          class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
          :class="focus === section.key ? 'border-amber-500/20 bg-amber-500/10 text-amber-300' : 'border-white/10 bg-white/5 text-stone-300 hover:bg-white/10'"
          @click="setFocus(section.key as 'overdue' | 'expiring' | 'suspended' | 'admin')"
        >
          {{ section.label }}
        </button>
      </div>

      <div class="grid gap-4 xl:grid-cols-2">
        <section v-if="isVisible('overdue')" class="rounded-3xl border border-white/10 bg-white/5 p-5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-white">Cobranças atrasadas</h2>
              <p class="mt-1 text-sm text-stone-400">Pendências financeiras abertas.</p>
            </div>
            <RouterLink :to="{ name: 'admin.billing', query: { overdue: '1' } }" class="text-xs font-semibold text-rose-300 hover:text-rose-200">
              Abrir módulo
            </RouterLink>
          </div>
          <div class="mt-4 space-y-3">
            <div v-for="invoice in alerts.overdue_invoices" :key="invoice.id" class="rounded-2xl border border-white/10 bg-black/20 p-4">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="font-medium text-white">{{ invoice.company.name }}</p>
                  <p class="mt-1 text-xs text-stone-500">{{ invoice.plan.name ?? 'Sem plano' }} • vence {{ fmtDate(invoice.due_at) }}</p>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2 text-right">
                  <p class="font-semibold text-rose-300">{{ fmtMoney(invoice.amount_cents, invoice.currency) }}</p>
                  <RouterLink
                    :to="{ name: 'admin.billing.show', params: { id: invoice.id } }"
                    class="inline-block text-xs text-sky-300 hover:text-sky-200"
                  >
                    Detalhes
                  </RouterLink>
                  <a v-if="invoice.invoice_url" :href="invoice.invoice_url" target="_blank" rel="noreferrer" class="mt-1 inline-block text-xs text-amber-300 hover:text-amber-200">Abrir cobrança</a>
                </div>
              </div>
            </div>
            <p v-if="alerts.overdue_invoices.length === 0" class="text-sm text-stone-500">Nenhuma cobrança atrasada no momento.</p>
          </div>
        </section>

        <section v-if="isVisible('expiring')" class="rounded-3xl border border-white/10 bg-white/5 p-5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-white">Assinaturas vencendo</h2>
              <p class="mt-1 text-sm text-stone-400">Casos com janela curta para atuação.</p>
            </div>
            <RouterLink :to="{ name: 'admin.subscriptions', query: { expiring: '1' } }" class="text-xs font-semibold text-violet-300 hover:text-violet-200">
              Abrir módulo
            </RouterLink>
          </div>
          <div class="mt-4 space-y-3">
            <div
              v-for="entry in alerts.expiring_subscriptions"
              :key="entry.company.id ?? String(entry.subscription.id)"
              class="rounded-2xl border border-white/10 bg-black/20 p-4"
            >
              <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <p class="font-medium text-white">{{ entry.company.name }}</p>
                  <p class="mt-1 text-xs text-stone-500">{{ entry.subscription.plan.name ?? 'Sem plano' }} • vence {{ fmtDate(entry.subscription.expires_at) }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                  <p class="text-xs font-semibold text-violet-300">{{ entry.subscription.days_until_expiration ?? '—' }} dia(s)</p>
                  <RouterLink
                    :to="{ name: 'admin.company', params: { id: entry.company.id } }"
                    class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
                  >
                    Empresa
                  </RouterLink>
                  <RouterLink
                    v-if="entry.company.id"
                    :to="{ name: 'admin.subscriptions.edit', params: { id: entry.company.id } }"
                    class="rounded-xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-300 transition hover:bg-sky-500/20"
                  >
                    Editar assinatura
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
                </div>
              </div>
            </div>
            <p v-if="alerts.expiring_subscriptions.length === 0" class="text-sm text-stone-500">Nenhuma assinatura crítica no momento.</p>
          </div>
        </section>

        <section v-if="isVisible('suspended')" class="rounded-3xl border border-white/10 bg-white/5 p-5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-white">Empresas suspensas</h2>
              <p class="mt-1 text-sm text-stone-400">Acesso interrompido e pendente de revisão.</p>
            </div>
            <RouterLink :to="{ name: 'admin.subscriptions', query: { status: 'suspended' } }" class="text-xs font-semibold text-amber-300 hover:text-amber-200">
              Abrir módulo
            </RouterLink>
          </div>
          <div class="mt-4 space-y-3">
            <div
              v-for="company in alerts.suspended_companies"
              :key="company.id"
              class="rounded-2xl border border-white/10 bg-black/20 p-4"
            >
              <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <p class="font-medium text-white">{{ company.name }}</p>
                  <p class="mt-1 text-xs text-stone-500">{{ company.subscription.plan.name ?? 'Sem plano' }} • {{ company.status }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                  <RouterLink
                    :to="{ name: 'admin.company', params: { id: company.id } }"
                    class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
                  >
                    Empresa
                  </RouterLink>
                  <RouterLink
                    :to="{ name: 'admin.subscriptions.edit', params: { id: company.id } }"
                    class="rounded-xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-300 transition hover:bg-sky-500/20"
                  >
                    Editar assinatura
                  </RouterLink>
                  <button
                    type="button"
                    class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-300 transition hover:bg-emerald-500/20 disabled:opacity-60"
                    :disabled="isRunning(`activate:${company.id}`)"
                    @click="reactivateCompany(company.id)"
                  >
                    {{ isRunning(`activate:${company.id}`) ? 'Reativando…' : 'Reativar' }}
                  </button>
                </div>
              </div>
            </div>
            <p v-if="alerts.suspended_companies.length === 0" class="text-sm text-stone-500">Nenhuma empresa suspensa no momento.</p>
          </div>
        </section>

        <section v-if="isVisible('admin')" class="rounded-3xl border border-white/10 bg-white/5 p-5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-white">Eventos administrativos recentes</h2>
              <p class="mt-1 text-sm text-stone-400">Mudanças globais mais recentes.</p>
            </div>
            <RouterLink :to="{ name: 'admin.audit', query: { category: 'admin' } }" class="text-xs font-semibold text-sky-300 hover:text-sky-200">
              Abrir auditoria
            </RouterLink>
          </div>
          <div class="mt-4 space-y-3">
            <RouterLink
              v-for="event in alerts.recent_admin_events"
              :key="event.id"
              :to="{ name: 'admin.company.audit', params: { id: event.company_id } }"
              class="block rounded-2xl border border-white/10 bg-black/20 p-4 transition hover:bg-black/30"
            >
              <p class="font-medium text-white">{{ event.company_name ?? `Empresa #${event.company_id}` }}</p>
              <p class="mt-1 text-xs text-stone-500">{{ event.event_type }} • {{ event.actor_name ?? 'Sistema' }} • {{ fmtDateTime(event.occurred_at) }}</p>
            </RouterLink>
            <p v-if="alerts.recent_admin_events.length === 0" class="text-sm text-stone-500">Sem eventos administrativos recentes.</p>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>
