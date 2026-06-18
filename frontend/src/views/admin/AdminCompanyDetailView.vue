<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ApiError } from '../../api/client'
import {
  activateAdminCompany,
  deleteAdminCompany,
  extendAdminCompanySubscription,
  getAdminCompany,
  listAdminCompanyAudits,
  listAdminPlans,
  suspendAdminCompany,
  type AdminAuditEntry,
  type AdminCompany,
  type AdminPlan,
  updateAdminCompanySubscription,
} from '../../api/admin'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const actionLoading = ref(false)
const error = ref('')
const company = ref<AdminCompany | null>(null)
const recentAudits = ref<AdminAuditEntry[]>([])
const plans = ref<AdminPlan[]>([])
const action = ref<'suspend' | 'activate' | 'delete' | 'subscription' | 'extend' | null>(null)
const confirmation = ref('')
const subscriptionForm = ref({
  plan_id: '',
  expires_at: '',
  started_at: '',
  status: 'active',
  extend_months: '1',
})

const statusLabels: Record<string, string> = {
  active: 'Ativa',
  suspended: 'Suspensa',
  cancelled: 'Cancelada',
}

const statusClasses: Record<string, string> = {
  active: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/20',
  suspended: 'bg-amber-500/15 text-amber-300 border-amber-500/20',
  cancelled: 'bg-rose-500/15 text-rose-300 border-rose-500/20',
}

const canConfirm = computed(() => {
  if (!company.value || !action.value) return false
  if (action.value !== 'delete') return true
  return confirmation.value === company.value.name
})

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

function limitLabel(value: number | null | undefined, suffix = '') {
  if (value === null || value === undefined) return 'Ilimitado'
  return `${value}${suffix}`
}

function applyCompany(data: AdminCompany) {
  company.value = data
  subscriptionForm.value.plan_id = String(data.subscription?.plan.id ?? '')
  subscriptionForm.value.expires_at = data.subscription?.expires_at?.slice(0, 10) ?? ''
  subscriptionForm.value.started_at = data.subscription?.started_at?.slice(0, 10) ?? ''
  subscriptionForm.value.status = data.subscription?.status ?? 'active'
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const companyId = Number(route.params.id)
    const [companyResponse, plansResponse] = await Promise.all([
      getAdminCompany(companyId),
      listAdminPlans(),
    ])

    plans.value = plansResponse.data
    applyCompany(companyResponse.data)
    recentAudits.value = (await listAdminCompanyAudits(companyId, { limit: 8 })).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao carregar detalhes da empresa.'
  } finally {
    loading.value = false
  }
}

function openAuditHistory() {
  if (!company.value) return
  router.push({ name: 'admin.company.audit', params: { id: company.value.id } })
}

function openAction(nextAction: 'suspend' | 'activate' | 'delete' | 'subscription' | 'extend') {
  action.value = nextAction
  confirmation.value = ''
}

function closeAction() {
  action.value = null
  confirmation.value = ''
}

async function submitAction() {
  if (!company.value || !action.value) return

  actionLoading.value = true
  error.value = ''

  try {
    if (action.value === 'suspend') {
      await suspendAdminCompany(company.value.id)
    } else if (action.value === 'activate') {
      await activateAdminCompany(company.value.id)
    } else if (action.value === 'subscription') {
      await updateAdminCompanySubscription(company.value.id, {
        plan_id: Number(subscriptionForm.value.plan_id),
        expires_at: subscriptionForm.value.expires_at,
        started_at: subscriptionForm.value.started_at || undefined,
        status: subscriptionForm.value.status,
      })
    } else if (action.value === 'extend') {
      await extendAdminCompanySubscription(company.value.id, {
        months: Number(subscriptionForm.value.extend_months || '1'),
      })
    } else {
      await deleteAdminCompany(company.value.id, confirmation.value)
      router.push({ name: 'admin.companies' })
      return
    }

    closeAction()
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao executar ação administrativa.'
  } finally {
    actionLoading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <RouterLink :to="{ name: 'admin.companies' }" class="text-sm text-stone-500 hover:text-stone-300">← Voltar para empresas</RouterLink>

    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando empresa…</div>

    <template v-else-if="company">
      <div class="flex flex-wrap gap-2">
        <RouterLink
          :to="{ name: 'admin.company', params: { id: company.id } }"
          class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-300"
        >
          Detalhes
        </RouterLink>
        <RouterLink
          :to="{ name: 'admin.company.audit', params: { id: company.id } }"
          class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
        >
          Histórico
        </RouterLink>
      </div>

      <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold text-white">{{ company.name }}</h1>
            <span :class="statusClasses[company.status] ?? 'bg-stone-500/15 text-stone-300 border-stone-500/20'" class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
              {{ statusLabels[company.status] ?? company.status }}
            </span>
          </div>
          <p class="mt-2 text-sm text-stone-400">
            {{ company.trade_name || 'Sem nome fantasia' }}
            <span v-if="company.slug" class="mx-2 text-stone-600">•</span>
            <span v-if="company.slug" class="font-mono text-stone-500">{{ company.slug }}</span>
          </p>
        </div>

        <div class="flex flex-wrap gap-2">
          <RouterLink
            :to="{ name: 'admin.companies.edit', params: { id: company.id } }"
            class="rounded-xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-300 transition hover:bg-sky-500/20"
          >
            Editar cadastro
          </RouterLink>
          <button
            type="button"
            class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
            @click="openAuditHistory"
          >
            Ver histórico
          </button>
          <RouterLink
            :to="{ name: 'admin.subscriptions.edit', params: { id: company.id } }"
            class="rounded-xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-300 transition hover:bg-sky-500/20"
          >
            Editar assinatura
          </RouterLink>
          <button
            type="button"
            class="rounded-xl border border-violet-500/20 bg-violet-500/10 px-3 py-2 text-xs font-semibold text-violet-300 transition hover:bg-violet-500/20"
            @click="openAction('extend')"
          >
            Renovar assinatura
          </button>
          <button
            v-if="company.status === 'active'"
            type="button"
            class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-300 transition hover:bg-amber-500/20"
            @click="openAction('suspend')"
          >
            Suspender acesso
          </button>
          <button
            v-else-if="company.status === 'suspended'"
            type="button"
            class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-300 transition hover:bg-emerald-500/20"
            @click="openAction('activate')"
          >
            Reativar acesso
          </button>
          <button
            type="button"
            class="rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-300 transition hover:bg-rose-500/20"
            @click="openAction('delete')"
          >
            Excluir empresa
          </button>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Membros</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ company.memberships_count }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">OS totais</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ company.service_orders_count }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Plano atual</p>
          <p class="mt-2 text-lg font-semibold text-white">{{ company.subscription?.plan.name ?? 'Sem plano' }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Validade</p>
          <p class="mt-2 text-lg font-semibold text-white">{{ fmtDate(company.subscription?.expires_at) }}</p>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="space-y-6">
          <section class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Cadastro da empresa</h2>
            <dl class="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <dt class="text-xs uppercase tracking-widest text-stone-500">Documento</dt>
                <dd class="mt-1 text-sm text-stone-200">{{ company.document ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs uppercase tracking-widest text-stone-500">E-mail</dt>
                <dd class="mt-1 text-sm text-stone-200">{{ company.email ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs uppercase tracking-widest text-stone-500">Telefone</dt>
                <dd class="mt-1 text-sm text-stone-200">{{ company.phone ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs uppercase tracking-widest text-stone-500">Website</dt>
                <dd class="mt-1 text-sm text-stone-200 break-all">{{ company.website ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs uppercase tracking-widest text-stone-500">Cidade / UF</dt>
                <dd class="mt-1 text-sm text-stone-200">{{ [company.city, company.state].filter(Boolean).join(' / ') || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs uppercase tracking-widest text-stone-500">Cadastro</dt>
                <dd class="mt-1 text-sm text-stone-200">{{ fmtDate(company.created_at) }}</dd>
              </div>
              <div class="md:col-span-2">
                <dt class="text-xs uppercase tracking-widest text-stone-500">URL do tenant</dt>
                <dd class="mt-1 text-sm text-stone-200 break-all">{{ company.tenant_url ?? '—' }}</dd>
              </div>
            </dl>
          </section>

          <section class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Assinatura e limites</h2>
            <div v-if="company.subscription" class="mt-4 space-y-4">
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <p class="text-xs uppercase tracking-widest text-stone-500">Status</p>
                  <p class="mt-1 text-sm text-stone-200">{{ statusLabels[company.subscription.status] ?? company.subscription.status }}</p>
                </div>
                <div>
                  <p class="text-xs uppercase tracking-widest text-stone-500">Início</p>
                  <p class="mt-1 text-sm text-stone-200">{{ fmtDate(company.subscription.started_at) }}</p>
                </div>
                <div>
                  <p class="text-xs uppercase tracking-widest text-stone-500">Vencimento</p>
                  <p class="mt-1 text-sm text-stone-200">{{ fmtDate(company.subscription.expires_at) }}</p>
                </div>
                <div>
                  <p class="text-xs uppercase tracking-widest text-stone-500">Dias restantes</p>
                  <p class="mt-1 text-sm text-stone-200">{{ company.subscription.days_until_expiration }}</p>
                </div>
              </div>

              <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                  <p class="text-xs uppercase tracking-widest text-stone-500">Usuários</p>
                  <p class="mt-1 text-sm text-white">{{ company.subscription.usage.active_users }} / {{ limitLabel(company.subscription.plan.limits.max_users) }}</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                  <p class="text-xs uppercase tracking-widest text-stone-500">OS no mês</p>
                  <p class="mt-1 text-sm text-white">{{ company.subscription.usage.service_orders_this_month }} / {{ limitLabel(company.subscription.plan.limits.max_service_orders_per_month) }}</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                  <p class="text-xs uppercase tracking-widest text-stone-500">Storage</p>
                  <p class="mt-1 text-sm text-white">{{ company.subscription.usage.attachments_storage_mb }} MB / {{ limitLabel(company.subscription.plan.limits.max_attachments_storage_mb, ' MB') }}</p>
                </div>
              </div>
            </div>
            <p v-else class="mt-4 text-sm text-stone-500">Nenhuma assinatura vinculada a esta empresa.</p>
          </section>
        </div>

        <section class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Faturas recentes</h2>
          <div v-if="company.recent_invoices?.length" class="mt-4 space-y-3">
            <div
              v-for="invoice in company.recent_invoices"
              :key="invoice.id"
              class="rounded-xl border border-white/10 bg-black/20 p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-medium text-white">{{ fmtMoney(invoice.amount_cents, invoice.currency) }}</p>
                  <p class="mt-1 text-xs text-stone-500">Vencimento: {{ fmtDate(invoice.due_at) }}</p>
                  <p class="text-xs text-stone-500">Pago em: {{ fmtDateTime(invoice.paid_at) }}</p>
                </div>
                <span class="rounded-full border border-white/10 px-2.5 py-1 text-xs font-semibold text-stone-300">
                  {{ invoice.status }}
                </span>
              </div>
              <div class="mt-3 flex flex-wrap gap-2">
                <a
                  v-if="invoice.invoice_url"
                  :href="invoice.invoice_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="rounded-lg border border-sky-500/20 bg-sky-500/10 px-3 py-1.5 text-xs font-semibold text-sky-300 transition hover:bg-sky-500/20"
                >
                  Cobrança
                </a>
                <a
                  v-if="invoice.pdf_url"
                  :href="invoice.pdf_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
                >
                  PDF
                </a>
              </div>
            </div>
          </div>
          <p v-else class="mt-4 text-sm text-stone-500">Nenhuma fatura registrada até o momento.</p>
        </section>

        <section class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Últimos eventos</h2>
            <button type="button" class="text-xs font-semibold text-amber-300 hover:text-amber-200" @click="openAuditHistory">
              Histórico completo
            </button>
          </div>
          <div v-if="recentAudits.length" class="mt-4 space-y-3">
            <div
              v-for="audit in recentAudits"
              :key="audit.id"
              class="rounded-xl border border-white/10 bg-black/20 p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-medium text-white">{{ audit.event_type }}</p>
                  <p class="mt-1 text-xs text-stone-500">
                    {{ audit.category }} • {{ audit.actor_name ?? 'Sistema' }} • {{ audit.actor_type === 'super_admin' ? 'super-admin' : 'tenant' }}
                  </p>
                </div>
                <p class="text-xs text-stone-500">{{ fmtDateTime(audit.occurred_at) }}</p>
              </div>
            </div>
          </div>
          <p v-else class="mt-4 text-sm text-stone-500">Nenhum evento registrado para esta empresa.</p>
        </section>
      </div>
    </template>

    <div v-if="action && company" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
      <div class="w-full max-w-xl rounded-3xl border border-white/10 bg-stone-950 p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 class="text-xl font-semibold text-white">
              {{
                action === 'suspend'
                  ? 'Suspender empresa'
                  : action === 'activate'
                    ? 'Reativar empresa'
                    : action === 'subscription'
                      ? 'Definir plano e validade'
                      : action === 'extend'
                        ? 'Renovar assinatura'
                        : 'Excluir empresa'
              }}
            </h2>
            <p class="mt-1 text-sm text-stone-400">{{ company.name }}</p>
          </div>
          <button type="button" class="text-stone-500 hover:text-stone-300" @click="closeAction">Fechar</button>
        </div>

        <div class="mt-5 grid gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-stone-300 md:grid-cols-2">
          <div>
            <p class="text-xs uppercase tracking-widest text-stone-500">Status</p>
            <p class="mt-1">{{ statusLabels[company.status] ?? company.status }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-widest text-stone-500">Plano</p>
            <p class="mt-1">{{ company.subscription?.plan.name ?? 'Sem plano' }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-widest text-stone-500">Validade</p>
            <p class="mt-1">{{ fmtDate(company.subscription?.expires_at) }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-widest text-stone-500">OS</p>
            <p class="mt-1">{{ company.service_orders_count }}</p>
          </div>
        </div>

        <div class="mt-5 space-y-3 text-sm text-stone-300">
          <p v-if="action === 'suspend'">Usuários dessa empresa deixarão de autenticar até reativação explícita.</p>
          <p v-else-if="action === 'activate'">A empresa volta a autenticar normalmente, respeitando memberships ativos.</p>
          <template v-else-if="action === 'subscription'">
            <div class="grid gap-3 md:grid-cols-3">
              <select v-model="subscriptionForm.plan_id" class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white">
                <option value="">Selecione um plano</option>
                <option v-for="plan in plans" :key="plan.id" :value="String(plan.id)">{{ plan.name }}</option>
              </select>
              <input v-model="subscriptionForm.expires_at" type="date" class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white" />
              <select v-model="subscriptionForm.status" class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white">
                <option value="active">Ativa</option>
                <option value="suspended">Suspensa</option>
                <option value="cancelled">Cancelada</option>
              </select>
            </div>
          </template>
          <template v-else-if="action === 'extend'">
            <p>Renova a validade da assinatura a partir da data atual ou do vencimento vigente, o que for maior.</p>
            <select v-model="subscriptionForm.extend_months" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white">
              <option value="1">+1 mês</option>
              <option value="3">+3 meses</option>
              <option value="6">+6 meses</option>
              <option value="12">+12 meses</option>
            </select>
          </template>
          <template v-else>
            <p>A exclusão é lógica. Para confirmar, digite exatamente o nome da empresa.</p>
            <input
              v-model="confirmation"
              type="text"
              class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white outline-none transition placeholder:text-stone-500 focus:border-rose-500/40"
              :placeholder="company.name"
            />
          </template>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-300 hover:bg-white/5" @click="closeAction">
            Cancelar
          </button>
          <button
            type="button"
            :disabled="(!canConfirm && !['subscription', 'extend'].includes(action ?? '')) || actionLoading"
            class="rounded-xl px-4 py-2 text-sm font-semibold text-stone-950 disabled:opacity-50"
            :class="action === 'delete'
              ? 'bg-rose-400 hover:bg-rose-300'
              : action === 'suspend'
                ? 'bg-amber-400 hover:bg-amber-300'
                : action === 'extend'
                  ? 'bg-violet-400 hover:bg-violet-300'
                  : action === 'subscription'
                    ? 'bg-sky-400 hover:bg-sky-300'
                    : 'bg-emerald-400 hover:bg-emerald-300'"
            @click="submitAction"
          >
            {{
              actionLoading
                ? 'Processando…'
                : action === 'delete'
                  ? 'Excluir'
                  : action === 'suspend'
                    ? 'Suspender'
                    : action === 'extend'
                      ? 'Renovar'
                      : action === 'subscription'
                        ? 'Salvar plano'
                        : 'Reativar'
            }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
