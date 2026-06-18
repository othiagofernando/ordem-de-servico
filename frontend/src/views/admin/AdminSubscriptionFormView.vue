<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { ApiError } from '../../api/client'
import {
  extendAdminCompanySubscription,
  getAdminCompany,
  listAdminPlans,
  updateAdminCompanySubscription,
  type AdminCompany,
  type AdminPlan,
} from '../../api/admin'
import { documentMask } from '../../utils/masks'

const route = useRoute()

const loading = ref(true)
const saving = ref(false)
const renewing = ref(false)
const error = ref('')
const notice = ref('')
const company = ref<AdminCompany | null>(null)
const plans = ref<AdminPlan[]>([])

const form = reactive({
  plan_id: '',
  started_at: '',
  expires_at: '',
  status: 'active',
  renew_months: '1',
})

const selectedPlan = computed(() => plans.value.find((plan) => String(plan.id) === form.plan_id) ?? null)

const today = new Date().toISOString().slice(0, 10)

const expiresAtError = computed(() => {
  if (!form.expires_at) return ''
  if (form.expires_at < today) return 'A data de validade não pode ser no passado.'
  if (form.started_at && form.expires_at <= form.started_at) return 'A validade deve ser posterior à data de início.'
  return ''
})

const startedAtError = computed(() => {
  if (!form.started_at || !form.expires_at) return ''
  if (form.started_at >= form.expires_at) return 'O início deve ser anterior à validade.'
  return ''
})

const formHasErrors = computed(() => !!(expiresAtError.value || startedAtError.value))

function fmtDate(value: string | null | undefined) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(new Date(value))
}

function fmtMoney(cents: number | null, currency = 'BRL') {
  if (cents === null) return 'Sob consulta'
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency })
}

function limitLabel(value: number | null | undefined, suffix = '') {
  if (value === null || value === undefined) return 'Ilimitado'
  return `${value}${suffix}`
}

function applyCompany(data: AdminCompany) {
  company.value = data
  form.plan_id = data.subscription?.plan.id ? String(data.subscription.plan.id) : ''
  form.started_at = data.subscription?.started_at?.slice(0, 10) ?? ''
  form.expires_at = data.subscription?.expires_at?.slice(0, 10) ?? ''
  form.status = data.subscription?.status ?? 'active'
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

    applyCompany(companyResponse.data)
    plans.value = plansResponse.data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao carregar assinatura da empresa.'
  } finally {
    loading.value = false
  }
}

async function submit() {
  if (!company.value) return

  saving.value = true
  error.value = ''
  notice.value = ''

  try {
    await updateAdminCompanySubscription(company.value.id, {
      plan_id: Number(form.plan_id),
      started_at: form.started_at || undefined,
      expires_at: form.expires_at,
      status: form.status,
    })

    notice.value = 'Assinatura atualizada com sucesso.'
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao salvar assinatura.'
  } finally {
    saving.value = false
  }
}

async function renew() {
  if (!company.value) return

  renewing.value = true
  error.value = ''
  notice.value = ''

  try {
    await extendAdminCompanySubscription(company.value.id, {
      months: Number(form.renew_months || '1'),
    })

    notice.value = 'Assinatura renovada com sucesso.'
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao renovar assinatura.'
  } finally {
    renewing.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <RouterLink :to="{ name: 'admin.subscriptions' }" class="text-sm text-stone-500 hover:text-stone-300">← Assinaturas</RouterLink>
      <RouterLink
        v-if="company"
        :to="{ name: 'admin.company', params: { id: company.id } }"
        class="text-sm text-stone-500 hover:text-stone-300"
      >
        Voltar para empresa
      </RouterLink>
    </div>

    <div>
      <h1 class="text-2xl font-semibold text-white">Editar Assinatura</h1>
      <p class="mt-1 text-sm text-stone-400">
        Defina plano, datas e status operacional da assinatura da empresa selecionada.
      </p>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <div v-if="notice" class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
      {{ notice }}
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando assinatura…</div>

    <template v-else-if="company">
      <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
        <div class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr_0.8fr_0.8fr]">
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Empresa</p>
            <p class="mt-2 text-lg font-semibold text-white">{{ company.name }}</p>
            <p class="mt-1 text-sm text-stone-400">{{ company.document ? documentMask(company.document) : (company.email || 'Sem identificador') }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Plano atual</p>
            <p class="mt-2 text-sm font-semibold text-white">{{ company.subscription?.plan.name ?? 'Sem plano' }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Validade</p>
            <p class="mt-2 text-sm font-semibold text-white">{{ fmtDate(company.subscription?.expires_at) }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Status</p>
            <p class="mt-2 text-sm font-semibold text-white">{{ company.subscription?.status ?? 'sem assinatura' }}</p>
          </div>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
          <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Configuração da assinatura</h2>
          <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="md:col-span-2">
              <label class="mb-1.5 block text-sm text-stone-300">Plano *</label>
              <select v-model="form.plan_id" required class="input">
                <option value="">Selecione um plano</option>
                <option v-for="plan in plans" :key="plan.id" :value="String(plan.id)">{{ plan.name }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">Início</label>
              <input
                v-model="form.started_at"
                type="date"
                :class="['input', startedAtError ? 'border-red-500/60 focus:border-red-500' : '']"
              />
              <p v-if="startedAtError" class="mt-1.5 text-xs text-red-400">{{ startedAtError }}</p>
            </div>
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">Validade *</label>
              <input
                v-model="form.expires_at"
                required
                type="date"
                :min="today"
                :class="['input', expiresAtError ? 'border-red-500/60 focus:border-red-500' : '']"
              />
              <p v-if="expiresAtError" class="mt-1.5 text-xs text-red-400">{{ expiresAtError }}</p>
            </div>
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">Status</label>
              <select v-model="form.status" class="input">
                <option value="active">Ativa</option>
                <option value="suspended">Suspensa</option>
                <option value="cancelled">Cancelada</option>
              </select>
            </div>
          </div>

          <div v-if="selectedPlan" class="mt-5 grid gap-3 md:grid-cols-4">
            <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
              <p class="text-xs uppercase tracking-widest text-stone-500">Preço</p>
              <p class="mt-2 text-sm font-semibold text-white">{{ fmtMoney(selectedPlan.reference_price_cents) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
              <p class="text-xs uppercase tracking-widest text-stone-500">Usuários</p>
              <p class="mt-2 text-sm font-semibold text-white">{{ limitLabel(selectedPlan.limits.max_users) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
              <p class="text-xs uppercase tracking-widest text-stone-500">OS/mês</p>
              <p class="mt-2 text-sm font-semibold text-white">{{ limitLabel(selectedPlan.limits.max_service_orders_per_month) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
              <p class="text-xs uppercase tracking-widest text-stone-500">Storage</p>
              <p class="mt-2 text-sm font-semibold text-white">{{ limitLabel(selectedPlan.limits.max_attachments_storage_mb, ' MB') }}</p>
            </div>
          </div>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
          <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Renovação rápida</h2>
          <div class="mt-5 flex flex-col gap-4 md:flex-row md:items-end">
            <div class="md:w-60">
              <label class="mb-1.5 block text-sm text-stone-300">Adicionar meses</label>
              <select v-model="form.renew_months" class="input">
                <option value="1">+1 mês</option>
                <option value="3">+3 meses</option>
                <option value="6">+6 meses</option>
                <option value="12">+12 meses</option>
              </select>
            </div>
            <button
              type="button"
              :disabled="renewing"
              class="rounded-xl border border-violet-500/20 bg-violet-500/10 px-6 py-2.5 text-sm font-semibold text-violet-300 transition hover:bg-violet-500/20 disabled:opacity-50"
              @click="renew"
            >
              {{ renewing ? 'Renovando…' : 'Renovar assinatura' }}
            </button>
          </div>
        </section>

        <div class="flex flex-wrap gap-3">
          <button
            type="submit"
            :disabled="saving || formHasErrors"
            class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
          >
            {{ saving ? 'Salvando…' : 'Salvar assinatura' }}
          </button>
          <RouterLink
            :to="{ name: 'admin.company', params: { id: company.id } }"
            class="rounded-xl border border-white/10 px-6 py-2.5 text-sm text-stone-400 transition hover:text-stone-200"
          >
            Ver empresa
          </RouterLink>
        </div>
      </form>
    </template>
  </div>
</template>

<style scoped>
@reference "../../style.css";
</style>
