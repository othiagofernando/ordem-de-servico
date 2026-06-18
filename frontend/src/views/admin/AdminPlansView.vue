<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { ApiError } from '../../api/client'
import { listAdminPlans, type AdminPlan } from '../../api/admin'

const plans = ref<AdminPlan[]>([])
const loading = ref(true)
const error = ref('')

const summary = computed(() => ({
  plans: plans.value.length,
  subscribedCompanies: plans.value.reduce((sum, plan) => sum + plan.subscribed_companies_count, 0),
  activeCompanies: plans.value.reduce((sum, plan) => sum + plan.active_companies_count, 0),
  expiringSoon: plans.value.reduce((sum, plan) => sum + plan.expiring_next_15_days_count, 0),
}))

function fmtMoney(cents: number | null) {
  if (cents === null) return 'Sob consulta'
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function limitLabel(value: number | null | undefined, suffix = '') {
  if (value === null || value === undefined) return 'Ilimitado'
  return `${value}${suffix}`
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    plans.value = (await listAdminPlans()).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao carregar catálogo de planos.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Planos</h1>
        <p class="mt-1 text-sm text-stone-400">
          Catálogo global de assinatura com visão de ocupação e atalhos para navegar pelas empresas de cada plano.
        </p>
      </div>

      <RouterLink
        :to="{ name: 'admin.plans.create' }"
        class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
      >
        Novo plano
      </RouterLink>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl border border-sky-500/20 bg-sky-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-sky-300">Planos cadastrados</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary.plans }}</p>
      </div>
      <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-300">Empresas vinculadas</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary.subscribedCompanies }}</p>
      </div>
      <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">Assinaturas ativas</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary.activeCompanies }}</p>
      </div>
      <div class="rounded-2xl border border-violet-500/20 bg-violet-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-violet-300">Vencendo em 15 dias</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary.expiringSoon }}</p>
      </div>
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando planos…</div>

    <div v-else class="grid gap-4 xl:grid-cols-2">
      <section
        v-for="plan in plans"
        :key="plan.id"
        class="rounded-3xl border border-white/10 bg-white/5 p-6"
      >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-3">
              <h2 class="text-xl font-semibold text-white">{{ plan.name }}</h2>
              <span class="rounded-full border border-white/10 px-2.5 py-1 text-xs font-semibold text-stone-400">
                {{ plan.slug }}
              </span>
            </div>
            <p class="mt-2 text-sm text-stone-400">Preço de referência: <span class="text-stone-200">{{ fmtMoney(plan.reference_price_cents) }}</span></p>
          </div>

          <div class="flex flex-wrap gap-2">
            <RouterLink
              :to="{ name: 'admin.plans.edit', params: { id: plan.id } }"
              class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-300 transition hover:bg-amber-500/20"
            >
              Editar
            </RouterLink>
            <RouterLink
              :to="{ name: 'admin.companies', query: { plan_id: String(plan.id) } }"
              class="rounded-xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-300 transition hover:bg-sky-500/20"
            >
              Ver empresas neste plano
            </RouterLink>
            <RouterLink
              :to="{ name: 'admin.subscriptions', query: { plan_id: String(plan.id) } }"
              class="rounded-xl border border-violet-500/20 bg-violet-500/10 px-3 py-2 text-xs font-semibold text-violet-300 transition hover:bg-violet-500/20"
            >
              Ver assinaturas
            </RouterLink>
          </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
          <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
            <p class="text-xs uppercase tracking-widest text-stone-500">Vinculadas</p>
            <p class="mt-2 text-2xl font-semibold text-white">{{ plan.subscribed_companies_count }}</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
            <p class="text-xs uppercase tracking-widest text-stone-500">Ativas</p>
            <p class="mt-2 text-2xl font-semibold text-white">{{ plan.active_companies_count }}</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
            <p class="text-xs uppercase tracking-widest text-stone-500">Vencendo 15d</p>
            <p class="mt-2 text-2xl font-semibold text-white">{{ plan.expiring_next_15_days_count }}</p>
          </div>
        </div>

        <dl class="mt-5 grid gap-3 text-sm text-stone-300 sm:grid-cols-3">
          <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
            <dt class="text-xs uppercase tracking-widest text-stone-500">Usuários</dt>
            <dd class="mt-2 font-semibold text-white">{{ limitLabel(plan.limits.max_users) }}</dd>
          </div>
          <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
            <dt class="text-xs uppercase tracking-widest text-stone-500">OS por mês</dt>
            <dd class="mt-2 font-semibold text-white">{{ limitLabel(plan.limits.max_service_orders_per_month) }}</dd>
          </div>
          <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
            <dt class="text-xs uppercase tracking-widest text-stone-500">Armazenamento</dt>
            <dd class="mt-2 font-semibold text-white">{{ limitLabel(plan.limits.max_attachments_storage_mb, ' MB') }}</dd>
          </div>
        </dl>
      </section>
    </div>
  </div>
</template>
