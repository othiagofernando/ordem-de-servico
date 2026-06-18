<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { ApiError } from '../../api/client'
import { getAdminCompany, listAdminCompanyAudits, type AdminAuditEntry, type AdminCompany } from '../../api/admin'

const route = useRoute()

const company = ref<AdminCompany | null>(null)
const audits = ref<AdminAuditEntry[]>([])
const loading = ref(true)
const error = ref('')
const category = ref('')
const eventType = ref('')

function fmtDateTime(value: string | null | undefined) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const companyId = Number(route.params.id)
    const [companyResponse, auditsResponse] = await Promise.all([
      getAdminCompany(companyId),
      listAdminCompanyAudits(companyId, {
        category: category.value || undefined,
        event_type: eventType.value || undefined,
      }),
    ])

    company.value = companyResponse.data
    audits.value = auditsResponse.data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao carregar histórico da empresa.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <RouterLink :to="{ name: 'admin.companies' }" class="text-sm text-stone-500 hover:text-stone-300">← Empresas</RouterLink>
      <RouterLink :to="{ name: 'admin.company', params: { id: route.params.id } }" class="text-sm text-stone-500 hover:text-stone-300">Voltar aos detalhes</RouterLink>
    </div>

    <div>
      <div v-if="company" class="mb-4 flex flex-wrap gap-2">
        <RouterLink
          :to="{ name: 'admin.company', params: { id: company.id } }"
          class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
        >
          Detalhes
        </RouterLink>
        <RouterLink
          :to="{ name: 'admin.company.audit', params: { id: company.id } }"
          class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-300"
        >
          Histórico
        </RouterLink>
      </div>
      <h1 class="text-2xl font-semibold text-white">Histórico administrativo</h1>
      <p class="mt-1 text-sm text-stone-400">
        {{ company?.name ?? 'Empresa' }} — trilha de eventos administrativos, billing e mudanças relevantes.
      </p>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <form class="grid gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 md:grid-cols-[1fr_1fr_auto]" @submit.prevent="load">
      <input
        v-model="category"
        type="text"
        placeholder="Filtrar por categoria"
        class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white outline-none placeholder:text-stone-500"
      />
      <input
        v-model="eventType"
        type="text"
        placeholder="Filtrar por event_type"
        class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white outline-none placeholder:text-stone-500"
      />
      <button type="submit" class="rounded-2xl bg-amber-500 px-4 py-3 text-sm font-semibold text-stone-950 hover:bg-amber-400">
        Filtrar
      </button>
    </form>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando histórico…</div>

    <div v-else-if="audits.length === 0" class="rounded-2xl border border-white/10 bg-white/5 px-6 py-12 text-center text-stone-500">
      Nenhum evento encontrado para os filtros atuais.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="audit in audits"
        :key="audit.id"
        class="rounded-2xl border border-white/10 bg-white/5 p-5"
      >
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded-full border border-white/10 px-2.5 py-1 text-xs font-semibold text-stone-300">{{ audit.category }}</span>
              <span class="text-sm font-semibold text-white">{{ audit.event_type }}</span>
            </div>
            <p class="mt-2 text-sm text-stone-400">
              Ator: <span class="text-stone-200">{{ audit.actor_name ?? 'Sistema' }}</span>
              <span class="mx-2 text-stone-600">•</span>
              <span class="text-stone-500">{{ audit.actor_type === 'super_admin' ? 'super-admin' : 'tenant' }}</span>
            </p>
            <p class="mt-1 text-xs text-stone-500">
              Recurso: {{ audit.auditable_type ?? '—' }} #{{ audit.auditable_id ?? '—' }}
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <RouterLink
              :to="{ name: 'admin.audit.show', params: { id: audit.id } }"
              class="rounded-xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-300 transition hover:bg-sky-500/20"
            >
              Inspecionar
            </RouterLink>
            <p class="text-xs text-stone-500">{{ fmtDateTime(audit.occurred_at) }}</p>
          </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
          <div class="rounded-xl border border-white/10 bg-black/20 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Contexto</p>
            <pre class="mt-2 overflow-x-auto whitespace-pre-wrap text-xs text-stone-300">{{ JSON.stringify(audit.context, null, 2) }}</pre>
          </div>
          <div class="rounded-xl border border-white/10 bg-black/20 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Antes</p>
            <pre class="mt-2 overflow-x-auto whitespace-pre-wrap text-xs text-stone-300">{{ JSON.stringify(audit.before_values, null, 2) }}</pre>
          </div>
          <div class="rounded-xl border border-white/10 bg-black/20 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Depois</p>
            <pre class="mt-2 overflow-x-auto whitespace-pre-wrap text-xs text-stone-300">{{ JSON.stringify(audit.after_values, null, 2) }}</pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
