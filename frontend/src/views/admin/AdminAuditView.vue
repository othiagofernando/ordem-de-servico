<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ApiError } from '../../api/client'
import { listAdminAudits, type AdminAuditEntry } from '../../api/admin'

const audits = ref<AdminAuditEntry[]>([])
const loading = ref(true)
const error = ref('')
const companyId = ref('')
const category = ref('')
const eventType = ref('')
const route = useRoute()
const router = useRouter()

const quickFilters = computed(() => [
  { label: 'Admin', query: { category: 'admin' } },
  { label: 'Billing', query: { category: 'billing' } },
  { label: 'Criações', query: { event_type: 'company.created' } },
  { label: 'Atualizações', query: { event_type: 'company.updated' } },
  { label: 'Assinaturas', query: { category: 'billing', event_type: 'subscription.updated' } },
])

function fmtDateTime(value: string | null | undefined) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    audits.value = (await listAdminAudits({
      company_id: companyId.value ? Number(companyId.value) : undefined,
      category: category.value || undefined,
      event_type: eventType.value || undefined,
      limit: 100,
    })).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao carregar auditoria global.'
  } finally {
    loading.value = false
  }
}

async function submitFilters() {
  await router.push({
    name: 'admin.audit',
    query: {
      ...(companyId.value ? { company_id: companyId.value } : {}),
      ...(category.value.trim() ? { category: category.value.trim() } : {}),
      ...(eventType.value.trim() ? { event_type: eventType.value.trim() } : {}),
    },
  })
}

onMounted(async () => {
  if (typeof route.query.company_id === 'string') companyId.value = route.query.company_id
  if (typeof route.query.category === 'string') category.value = route.query.category
  if (typeof route.query.event_type === 'string') eventType.value = route.query.event_type
  await load()
})

watch(
  () => [route.query.company_id, route.query.category, route.query.event_type],
  async ([nextCompanyId, nextCategory, nextEventType], [previousCompanyId, previousCategory, previousEventType]) => {
    if (nextCompanyId === previousCompanyId && nextCategory === previousCategory && nextEventType === previousEventType) return
    companyId.value = typeof nextCompanyId === 'string' ? nextCompanyId : ''
    category.value = typeof nextCategory === 'string' ? nextCategory : ''
    eventType.value = typeof nextEventType === 'string' ? nextEventType : ''
    await load()
  },
)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold text-white">Auditoria Global</h1>
      <p class="mt-1 text-sm text-stone-400">
        Eventos administrativos e operacionais entre empresas, acessíveis diretamente pelo menu lateral do super-admin.
      </p>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <form class="grid gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 lg:grid-cols-[180px_1fr_1fr_auto]" @submit.prevent="submitFilters">
      <input
        v-model="companyId"
        type="number"
        min="1"
        placeholder="ID da empresa"
        class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white outline-none placeholder:text-stone-500"
      />
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

    <div class="flex flex-wrap gap-2">
      <RouterLink
        :to="{ name: 'admin.audit' }"
        class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
      >
        Limpar filtros
      </RouterLink>
      <RouterLink
        v-for="filter in quickFilters"
        :key="filter.label"
        :to="{ name: 'admin.audit', query: filter.query }"
        class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
      >
        {{ filter.label }}
      </RouterLink>
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando auditoria…</div>

    <div v-else-if="audits.length === 0" class="rounded-2xl border border-white/10 bg-white/5 px-6 py-12 text-center text-stone-500">
      Nenhum evento encontrado para os filtros atuais.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="audit in audits"
        :key="audit.id"
        class="rounded-2xl border border-white/10 bg-white/5 p-5"
      >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded-full border border-white/10 px-2.5 py-1 text-xs font-semibold text-stone-300">{{ audit.category }}</span>
              <span class="text-sm font-semibold text-white">{{ audit.event_type }}</span>
            </div>
            <p class="mt-2 text-sm text-stone-400">
              Empresa:
              <RouterLink
                :to="{ name: 'admin.company', params: { id: audit.company_id } }"
                class="text-stone-200 hover:text-amber-300"
              >
                {{ audit.company_name ?? `#${audit.company_id}` }}
              </RouterLink>
              <span class="mx-2 text-stone-600">•</span>
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
            <RouterLink
              :to="{ name: 'admin.company.audit', params: { id: audit.company_id } }"
              class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-stone-300 transition hover:bg-white/10"
            >
              Histórico da empresa
            </RouterLink>
            <p class="text-xs text-stone-500">{{ fmtDateTime(audit.occurred_at) }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
