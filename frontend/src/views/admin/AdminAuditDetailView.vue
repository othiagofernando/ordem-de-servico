<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { ApiError } from '../../api/client'
import { getAdminAudit, type AdminAuditEntry } from '../../api/admin'
import AuditDiff from '../../components/AuditDiff.vue'

const route = useRoute()
const loading = ref(true)
const error = ref('')
const audit = ref<AdminAuditEntry | null>(null)

function fmtDateTime(value: string | null | undefined) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    audit.value = (await getAdminAudit(Number(route.params.id))).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao carregar evento de auditoria.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <RouterLink :to="{ name: 'admin.audit' }" class="text-sm text-stone-500 hover:text-stone-300">← Auditoria</RouterLink>
      <RouterLink
        v-if="audit?.company_id"
        :to="{ name: 'admin.company.audit', params: { id: audit.company_id } }"
        class="text-sm text-stone-500 hover:text-stone-300"
      >
        Histórico da empresa
      </RouterLink>
    </div>

    <div>
      <h1 class="text-2xl font-semibold text-white">Detalhe do Evento</h1>
      <p class="mt-1 text-sm text-stone-400">
        Inspeção completa do evento de auditoria, com contexto e diffs persistidos.
      </p>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando evento…</div>

    <template v-else-if="audit">
      <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded-full border border-white/10 px-2.5 py-1 text-xs font-semibold text-stone-300">{{ audit.category }}</span>
              <h2 class="text-xl font-semibold text-white">{{ audit.event_type }}</h2>
            </div>
            <p class="mt-2 text-sm text-stone-400">
              Empresa:
              <RouterLink
                :to="{ name: 'admin.company', params: { id: audit.company_id } }"
                class="text-stone-200 hover:text-amber-300"
              >
                {{ audit.company_name ?? `#${audit.company_id}` }}
              </RouterLink>
            </p>
          </div>

          <div class="text-sm text-stone-400">
            {{ fmtDateTime(audit.occurred_at) }}
          </div>
        </div>
      </section>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Ator</p>
          <p class="mt-2 text-sm font-semibold text-white">{{ audit.actor_name ?? 'Sistema' }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Origem</p>
          <p class="mt-2 text-sm font-semibold text-white">{{ audit.actor_type === 'super_admin' ? 'super-admin' : 'tenant' }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Recurso</p>
          <p class="mt-2 text-sm font-semibold text-white">{{ audit.auditable_type ?? '—' }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">ID recurso</p>
          <p class="mt-2 text-sm font-semibold text-white">{{ audit.auditable_id ?? '—' }}</p>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
          <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Contexto</h2>
          <div class="mt-4 rounded-2xl border border-white/10 bg-black/20 p-4">
            <pre class="overflow-x-auto whitespace-pre-wrap text-xs text-stone-300">{{ JSON.stringify(audit.context ?? null, null, 2) }}</pre>
          </div>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-6 xl:col-span-2">
          <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Alterações (Antes → Depois)</h2>
          <div class="mt-4">
            <AuditDiff :before="audit.before_values" :after="audit.after_values" />
          </div>
        </section>
      </div>
    </template>
  </div>
</template>
