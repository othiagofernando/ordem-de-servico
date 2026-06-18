<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '../../api/client'
import {
  activateAdminCompany,
  deleteAdminCompany,
  extendAdminCompanySubscription,
  getAdminCompany,
  listAdminCompanies,
  listAdminPlans,
  suspendAdminCompany,
  type AdminCompany,
  type AdminPlan,
  type AdminCompanySummary,
  updateAdminCompanySubscription,
} from '../../api/admin'

const route = useRoute()
const router = useRouter()
const companies = ref<AdminCompany[]>([])
const summary = ref<AdminCompanySummary | null>(null)
const loading = ref(true)
const error = ref('')
const search = ref('')
const selectedCompany = ref<AdminCompany | null>(null)
const action = ref<'suspend' | 'activate' | 'delete' | 'subscription' | 'extend' | null>(null)
const actionLoading = ref(false)
const confirmation = ref('')
const details = ref<AdminCompany | null>(null)
const plans = ref<AdminPlan[]>([])
const subscriptionForm = ref({
  plan_id: '',
  expires_at: '',
  started_at: '',
  status: 'active',
  extend_months: '1',
})

const activePlanId = computed(() => {
  const raw = route.query.plan_id
  const value = Array.isArray(raw) ? raw[0] : raw
  const numeric = Number(value)
  return Number.isInteger(numeric) && numeric > 0 ? numeric : null
})

const activePlan = computed(() => plans.value.find((plan) => plan.id === activePlanId.value) ?? null)

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
  if (!selectedCompany.value || !action.value) return false
  if (action.value !== 'delete') return true
  return confirmation.value === selectedCompany.value.name
})

function fmtDate(value: string | null | undefined) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(new Date(value))
}

async function loadCompanies(query = search.value) {
  loading.value = true
  error.value = ''
  try {
    const res = await listAdminCompanies({
      query,
      plan_id: activePlanId.value ?? undefined,
    })
    companies.value = res.data
    summary.value = res.summary
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao carregar empresas.'
  } finally {
    loading.value = false
  }
}

async function openAction(company: AdminCompany, nextAction: 'suspend' | 'activate' | 'delete' | 'subscription' | 'extend') {
  selectedCompany.value = company
  action.value = nextAction
  confirmation.value = ''
  details.value = null

  try {
    const res = await getAdminCompany(company.id)
    details.value = res.data
    subscriptionForm.value.plan_id = String(res.data.subscription?.plan.id ?? '')
    subscriptionForm.value.expires_at = res.data.subscription?.expires_at?.slice(0, 10) ?? ''
    subscriptionForm.value.started_at = res.data.subscription?.started_at?.slice(0, 10) ?? ''
    subscriptionForm.value.status = res.data.subscription?.status ?? 'active'
  } catch {
    details.value = company
  }
}

function openDetails(company: AdminCompany) {
  router.push({ name: 'admin.company', params: { id: company.id } })
}

function openAuditHistory(company: AdminCompany) {
  router.push({ name: 'admin.company.audit', params: { id: company.id } })
}

function closeAction() {
  selectedCompany.value = null
  action.value = null
  confirmation.value = ''
  details.value = null
}

async function submitAction() {
  if (!selectedCompany.value || !action.value || !canConfirm.value) return

  actionLoading.value = true
  error.value = ''

  try {
    if (action.value === 'suspend') {
      await suspendAdminCompany(selectedCompany.value.id)
    } else if (action.value === 'activate') {
      await activateAdminCompany(selectedCompany.value.id)
    } else if (action.value === 'subscription') {
      await updateAdminCompanySubscription(selectedCompany.value.id, {
        plan_id: Number(subscriptionForm.value.plan_id),
        expires_at: subscriptionForm.value.expires_at,
        started_at: subscriptionForm.value.started_at || undefined,
        status: subscriptionForm.value.status,
      })
    } else if (action.value === 'extend') {
      await extendAdminCompanySubscription(selectedCompany.value.id, {
        months: Number(subscriptionForm.value.extend_months || '1'),
      })
    } else {
      await deleteAdminCompany(selectedCompany.value.id, confirmation.value)
    }

    closeAction()
    await loadCompanies(search.value)
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao executar ação administrativa.'
  } finally {
    actionLoading.value = false
  }
}

onMounted(async () => {
  const plansResponse = await listAdminPlans()
  plans.value = plansResponse.data
  search.value = typeof route.query.q === 'string' ? route.query.q : ''
  await loadCompanies(search.value)
})

watch(
  () => [route.query.plan_id, route.query.q],
  async ([nextPlanId, nextQuery], [previousPlanId, previousQuery]) => {
    if (nextPlanId === previousPlanId && nextQuery === previousQuery) return
    search.value = typeof nextQuery === 'string' ? nextQuery : ''
    await loadCompanies(search.value)
  },
)

async function submitSearch() {
  await router.push({
    name: 'admin.companies',
    query: {
      ...(search.value.trim() ? { q: search.value.trim() } : {}),
      ...(activePlanId.value ? { plan_id: String(activePlanId.value) } : {}),
    },
  })
}

async function clearPlanFilter() {
  await router.push({
    name: 'admin.companies',
    query: {
      ...(search.value.trim() ? { q: search.value.trim() } : {}),
    },
  })
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Empresas</h1>
        <p class="mt-1 text-sm text-stone-400">
          Supervisão global das empresas cadastradas e do ciclo de vida de acesso.
        </p>
      </div>

      <div class="flex w-full max-w-3xl flex-col gap-3 lg:flex-row lg:items-center lg:justify-end">
        <form class="w-full max-w-md" @submit.prevent="submitSearch">
          <input
            v-model="search"
            type="search"
            class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition placeholder:text-stone-500 focus:border-amber-500/40"
            placeholder="Buscar por nome, CNPJ ou e-mail"
          />
        </form>
        <RouterLink
          :to="{ name: 'admin.companies.create' }"
          class="rounded-2xl bg-amber-500 px-4 py-3 text-center text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
        >
          Nova empresa
        </RouterLink>
      </div>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <div
      v-if="activePlan"
      class="flex flex-col gap-3 rounded-2xl border border-sky-500/20 bg-sky-500/10 px-4 py-4 lg:flex-row lg:items-center lg:justify-between"
    >
      <div>
        <p class="text-xs font-semibold uppercase tracking-widest text-sky-300">Filtro ativo</p>
        <p class="mt-1 text-sm text-sky-100">
          Exibindo empresas vinculadas ao plano <strong>{{ activePlan.name }}</strong>.
        </p>
      </div>
      <button
        type="button"
        class="rounded-xl border border-sky-400/20 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-200 transition hover:bg-sky-500/20"
        @click="clearPlanFilter"
      >
        Limpar filtro
      </button>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
      <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-300">Empresas ativas</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary?.active_companies ?? 0 }}</p>
      </div>
      <div class="rounded-2xl border border-sky-500/20 bg-sky-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-sky-300">OS no mês</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary?.service_orders_this_month ?? 0 }}</p>
      </div>
      <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">Novos cadastros 7d</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary?.new_companies_last_7_days ?? 0 }}</p>
      </div>
      <div class="rounded-2xl border border-violet-500/20 bg-violet-500/5 p-5 md:col-span-3">
        <p class="text-xs font-semibold uppercase tracking-widest text-violet-300">Vencendo em 15 dias</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary?.subscriptions_expiring_next_15_days ?? 0 }}</p>
      </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/5">
      <div v-if="loading" class="px-6 py-12 text-center text-stone-500">Carregando empresas…</div>

      <table v-else class="min-w-full divide-y divide-white/10 text-sm">
        <thead class="bg-black/20 text-left text-xs uppercase tracking-widest text-stone-500">
          <tr>
            <th class="px-4 py-3">Empresa</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Membros</th>
            <th class="px-4 py-3">OS</th>
            <th class="px-4 py-3">Plano</th>
            <th class="px-4 py-3">Validade</th>
            <th class="px-4 py-3">Cadastro</th>
            <th class="px-4 py-3 text-right">Ações</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <tr v-for="company in companies" :key="company.id" class="hover:bg-white/5">
            <td class="px-4 py-4">
              <div class="font-medium text-white">{{ company.name }}</div>
              <div class="mt-1 text-xs text-stone-500">{{ company.document || company.email || 'Sem identificador' }}</div>
            </td>
            <td class="px-4 py-4">
              <span :class="statusClasses[company.status] ?? 'bg-stone-500/15 text-stone-300 border-stone-500/20'" class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                {{ statusLabels[company.status] ?? company.status }}
              </span>
            </td>
            <td class="px-4 py-4 text-stone-300">{{ company.memberships_count }}</td>
            <td class="px-4 py-4 text-stone-300">{{ company.service_orders_count }}</td>
            <td class="px-4 py-4 text-stone-300">{{ company.subscription?.plan.name ?? 'Sem plano' }}</td>
            <td class="px-4 py-4 text-stone-400">{{ fmtDate(company.subscription?.expires_at) }}</td>
            <td class="px-4 py-4 text-stone-400">{{ fmtDate(company.created_at) }}</td>
            <td class="px-4 py-4">
              <div class="flex justify-end gap-2">
                <button
                  type="button"
                  class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
                  @click="openDetails(company)"
                >
                  Detalhes
                </button>
                <RouterLink
                  :to="{ name: 'admin.companies.edit', params: { id: company.id } }"
                  class="rounded-xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-300 transition hover:bg-sky-500/20"
                >
                  Editar
                </RouterLink>
                <button
                  type="button"
                  class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
                  @click="openAuditHistory(company)"
                >
                  Histórico
                </button>
                <button
                  type="button"
                  class="rounded-xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-300 transition hover:bg-sky-500/20"
                  @click="openAction(company, 'subscription')"
                >
                  Plano
                </button>
                <button
                  type="button"
                  class="rounded-xl border border-violet-500/20 bg-violet-500/10 px-3 py-2 text-xs font-semibold text-violet-300 transition hover:bg-violet-500/20"
                  @click="openAction(company, 'extend')"
                >
                  Renovar
                </button>
                <button
                  v-if="company.status === 'active'"
                  type="button"
                  class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-300 transition hover:bg-amber-500/20"
                  @click="openAction(company, 'suspend')"
                >
                  Suspender
                </button>
                <button
                  v-else-if="company.status === 'suspended'"
                  type="button"
                  class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-300 transition hover:bg-emerald-500/20"
                  @click="openAction(company, 'activate')"
                >
                  Reativar
                </button>
                <button
                  type="button"
                  class="rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-300 transition hover:bg-rose-500/20"
                  @click="openAction(company, 'delete')"
                >
                  Excluir
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="action && selectedCompany" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
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
            <p class="mt-1 text-sm text-stone-400">{{ selectedCompany.name }}</p>
          </div>
          <button type="button" class="text-stone-500 hover:text-stone-300" @click="closeAction">Fechar</button>
        </div>

        <div class="mt-5 grid gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-stone-300 md:grid-cols-2">
          <div>
            <p class="text-xs uppercase tracking-widest text-stone-500">Status</p>
            <p class="mt-1">{{ statusLabels[(details ?? selectedCompany).status] ?? (details ?? selectedCompany).status }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-widest text-stone-500">Cadastro</p>
            <p class="mt-1">{{ fmtDate((details ?? selectedCompany).created_at) }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-widest text-stone-500">Membros</p>
            <p class="mt-1">{{ (details ?? selectedCompany).memberships_count }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-widest text-stone-500">OS</p>
            <p class="mt-1">{{ (details ?? selectedCompany).service_orders_count }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-widest text-stone-500">Plano</p>
            <p class="mt-1">{{ (details ?? selectedCompany).subscription?.plan.name ?? 'Sem plano' }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-widest text-stone-500">Validade</p>
            <p class="mt-1">{{ fmtDate((details ?? selectedCompany).subscription?.expires_at) }}</p>
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
              :placeholder="selectedCompany.name"
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
