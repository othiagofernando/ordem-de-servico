<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { getAdminOverview, type AdminOverviewPayload } from '../../api/admin'
import { useApiError } from '../../composables/useApiError'
import ApiErrorAlert from '../../components/ApiErrorAlert.vue'
import { documentMask } from '../../utils/masks'

const overview = ref<AdminOverviewPayload | null>(null)
const loading = ref(true)
const { error, handleError } = useApiError()
const route = useRoute()
const router = useRouter()
const focus = ref<'expiring' | 'recent' | 'suspended' | ''>('')

const focusSections = computed(() => [
  { key: 'expiring', label: 'Vencendo em breve' },
  { key: 'recent', label: 'Cadastros recentes' },
  { key: 'suspended', label: 'Empresas suspensas' },
])

function fmtDate(value: string | null | undefined) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(new Date(value))
}

function isVisible(section: 'expiring' | 'recent' | 'suspended') {
  return !focus.value || focus.value === section
}

async function setFocus(nextFocus: 'expiring' | 'recent' | 'suspended' | '') {
  focus.value = nextFocus
  await router.push({
    name: 'admin',
    query: nextFocus ? { focus: nextFocus } : {},
  })
}

async function load() {
  loading.value = true

  try {
    overview.value = await getAdminOverview()
  } catch (e) {
    handleError(e)
  } finally {
    loading.value = false
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
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Resumo do Super-Admin</h1>
        <p class="mt-1 text-sm text-stone-400">
          Visão geral dos módulos globais para orientar navegação, operação e acompanhamento do ambiente.
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <RouterLink :to="{ name: 'admin.companies' }" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10">
          Empresas
        </RouterLink>
        <RouterLink :to="{ name: 'admin.companies.create' }" class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-300 transition hover:bg-amber-500/20">
          Nova empresa
        </RouterLink>
        <RouterLink :to="{ name: 'admin.subscriptions' }" class="rounded-xl border border-violet-500/20 bg-violet-500/10 px-3 py-2 text-xs font-semibold text-violet-300 transition hover:bg-violet-500/20">
          Assinaturas
        </RouterLink>
        <RouterLink :to="{ name: 'admin.billing' }" class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-300 transition hover:bg-emerald-500/20">
          Cobranças
        </RouterLink>
        <RouterLink :to="{ name: 'admin.plans' }" class="rounded-xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-300 transition hover:bg-sky-500/20">
          Planos
        </RouterLink>
        <RouterLink :to="{ name: 'admin.audit' }" class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-300 transition hover:bg-amber-500/20">
          Auditoria
        </RouterLink>
      </div>
    </div>

    <ApiErrorAlert :error="error" />

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando resumo…</div>

    <template v-else-if="overview">
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <RouterLink :to="{ name: 'admin.companies' }" class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5 transition hover:bg-emerald-500/10">
          <p class="text-xs font-semibold uppercase tracking-widest text-emerald-300">Empresas ativas</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ overview.summary.active_companies }}</p>
        </RouterLink>
        <RouterLink :to="{ name: 'admin.audit', query: { category: 'service_orders' } }" class="rounded-2xl border border-sky-500/20 bg-sky-500/5 p-5 transition hover:bg-sky-500/10">
          <p class="text-xs font-semibold uppercase tracking-widest text-sky-300">OS no mês</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ overview.summary.service_orders_this_month }}</p>
        </RouterLink>
        <button type="button" class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5 text-left transition hover:bg-amber-500/10" @click="setFocus('recent')">
          <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">Novos cadastros 7d</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ overview.summary.new_companies_last_7_days }}</p>
        </button>
        <button type="button" class="rounded-2xl border border-violet-500/20 bg-violet-500/5 p-5 text-left transition hover:bg-violet-500/10" @click="setFocus('expiring')">
          <p class="text-xs font-semibold uppercase tracking-widest text-violet-300">Vencendo 15d</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ overview.summary.subscriptions_expiring_next_15_days }}</p>
        </button>
      </div>

      <div class="grid gap-4 md:grid-cols-3">
        <RouterLink :to="{ name: 'admin.plans' }" class="rounded-3xl border border-white/10 bg-white/5 p-5 transition hover:bg-white/10">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Planos</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ overview.summary.total_plans }}</p>
          <p class="mt-2 text-sm text-stone-400">Gerencie catálogo e distribuição entre empresas.</p>
        </RouterLink>
        <RouterLink :to="{ name: 'admin.subscriptions', query: { status: 'active' } }" class="rounded-3xl border border-white/10 bg-white/5 p-5 transition hover:bg-white/10">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Assinaturas ativas</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ overview.summary.active_subscriptions }}</p>
          <p class="mt-2 text-sm text-stone-400">Acesse rapidamente a carteira ativa.</p>
        </RouterLink>
        <RouterLink :to="{ name: 'admin.subscriptions', query: { status: 'suspended' } }" class="rounded-3xl border border-white/10 bg-white/5 p-5 transition hover:bg-white/10">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Assinaturas suspensas</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ overview.summary.suspended_subscriptions }}</p>
          <p class="mt-2 text-sm text-stone-400">Veja imediatamente os casos que exigem ação.</p>
        </RouterLink>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
          :class="!focus ? 'border-amber-500/20 bg-amber-500/10 text-amber-300' : 'border-white/10 bg-white/5 text-stone-300 hover:bg-white/10'"
          @click="setFocus('')"
        >
          Todas as filas
        </button>
        <button
          v-for="section in focusSections"
          :key="section.key"
          type="button"
          class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
          :class="focus === section.key ? 'border-amber-500/20 bg-amber-500/10 text-amber-300' : 'border-white/10 bg-white/5 text-stone-300 hover:bg-white/10'"
          @click="setFocus(section.key as 'expiring' | 'recent' | 'suspended')"
        >
          {{ section.label }}
        </button>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <section v-if="isVisible('expiring')" class="rounded-3xl border border-white/10 bg-white/5 p-5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-white">Vencendo em breve</h2>
              <p class="mt-1 text-sm text-stone-400">Empresas que pedem atuação mais imediata.</p>
            </div>
            <RouterLink :to="{ name: 'admin.subscriptions', query: { expiring: '1' } }" class="text-xs font-semibold text-violet-300 hover:text-violet-200">
              Ver todas
            </RouterLink>
          </div>
          <div class="mt-4 space-y-3">
            <div
              v-for="company in overview.expiring_companies"
              :key="company.id"
              class="rounded-2xl border border-white/10 bg-black/20 p-4"
            >
              <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <p class="font-medium text-white">{{ company.name }}</p>
                  <p class="mt-1 text-xs text-stone-500">{{ company.document ? documentMask(company.document) : 'Sem documento' }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                  <p class="text-xs font-semibold text-violet-300">{{ company.subscription?.days_until_expiration ?? '—' }} dia(s)</p>
                  <p class="text-xs text-stone-500">{{ company.subscription?.plan.name ?? 'Sem plano' }}</p>
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
                </div>
              </div>
            </div>
            <p v-if="overview.expiring_companies.length === 0" class="text-sm text-stone-500">Nenhuma assinatura crítica no momento.</p>
          </div>
        </section>

        <section v-if="isVisible('recent')" class="rounded-3xl border border-white/10 bg-white/5 p-5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-white">Cadastros recentes</h2>
              <p class="mt-1 text-sm text-stone-400">Últimas empresas incorporadas ao ambiente.</p>
            </div>
            <RouterLink :to="{ name: 'admin.companies' }" class="text-xs font-semibold text-sky-300 hover:text-sky-200">
              Ver empresas
            </RouterLink>
          </div>
          <div class="mt-4 space-y-3">
            <div
              v-for="company in overview.recent_companies"
              :key="company.id"
              class="rounded-2xl border border-white/10 bg-black/20 p-4"
            >
              <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <p class="font-medium text-white">{{ company.name }}</p>
                  <p class="mt-1 text-xs text-stone-500">{{ company.subscription?.plan.name ?? 'Sem plano' }} • {{ fmtDate(company.created_at) }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                  <RouterLink
                    :to="{ name: 'admin.company', params: { id: company.id } }"
                    class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
                  >
                    Empresa
                  </RouterLink>
                  <RouterLink
                    :to="{ name: 'admin.companies.edit', params: { id: company.id } }"
                    class="rounded-xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-300 transition hover:bg-sky-500/20"
                  >
                    Editar cadastro
                  </RouterLink>
                  <RouterLink
                    :to="{ name: 'admin.subscriptions.edit', params: { id: company.id } }"
                    class="rounded-xl border border-violet-500/20 bg-violet-500/10 px-3 py-2 text-xs font-semibold text-violet-300 transition hover:bg-violet-500/20"
                  >
                    Assinatura
                  </RouterLink>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section v-if="isVisible('suspended')" class="rounded-3xl border border-white/10 bg-white/5 p-5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-white">Empresas suspensas</h2>
              <p class="mt-1 text-sm text-stone-400">Itens que merecem revisão ou reativação.</p>
            </div>
            <RouterLink :to="{ name: 'admin.subscriptions', query: { status: 'suspended' } }" class="text-xs font-semibold text-amber-300 hover:text-amber-200">
              Ver carteira
            </RouterLink>
          </div>
          <div class="mt-4 space-y-3">
            <div
              v-for="company in overview.suspended_companies"
              :key="company.id"
              class="rounded-2xl border border-white/10 bg-black/20 p-4"
            >
              <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <p class="font-medium text-white">{{ company.name }}</p>
                  <p class="mt-1 text-xs text-stone-500">{{ company.subscription?.plan.name ?? 'Sem plano' }} • {{ company.status }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
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
                  <RouterLink
                    :to="{ name: 'admin.company.audit', params: { id: company.id } }"
                    class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-300 transition hover:bg-amber-500/20"
                  >
                    Histórico
                  </RouterLink>
                </div>
              </div>
            </div>
            <p v-if="overview.suspended_companies.length === 0" class="text-sm text-stone-500">Nenhuma empresa suspensa agora.</p>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>
