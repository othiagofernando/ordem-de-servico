<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '../../api/client'
import { getAuditLogs, type AuditLogEntry } from '../../api/audit'
import AuditDiff from '../../components/AuditDiff.vue'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const error = ref('')
const entries = ref<AuditLogEntry[]>([])
const category = ref('')
const eventType = ref('')
const expanded = ref<number | null>(null)

const CATEGORY_LABELS: Record<string, string> = {
  membership: 'Membros',
  service_order_document: 'Documentos',
  service_order_financial: 'Financeiro',
  service_order_closure: 'Encerramento',
  service_order_attachment: 'Anexos',
  service_order_warranty: 'Garantia',
  quote_portal: 'Portal',
  admin: 'Admin',
  billing: 'Faturamento',
}

const EVENT_TYPE_LABELS: Record<string, string> = {
  membership_authorization_changed: 'Perfil alterado',
  printable_snapshot_issued: 'Documento emitido',
  printable_snapshot_reissued: 'Documento reemitido',
  payment_recorded: 'Pagamento registrado',
  service_order_delivered: 'OS entregue',
  attachment_uploaded: 'Anexo enviado',
  attachment_deleted: 'Anexo removido',
  quote_approved: 'Orçamento aprovado',
  quote_rejected: 'Orçamento recusado',
  warranty_decision_recorded: 'Decisão de garantia',
}

const CATEGORY_COLORS: Record<string, string> = {
  membership: 'border-violet-500/20 bg-violet-500/10 text-violet-300',
  service_order_document: 'border-sky-500/20 bg-sky-500/10 text-sky-300',
  service_order_financial: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300',
  service_order_closure: 'border-amber-500/20 bg-amber-500/10 text-amber-300',
  service_order_attachment: 'border-stone-500/20 bg-stone-500/10 text-stone-300',
  service_order_warranty: 'border-rose-500/20 bg-rose-500/10 text-rose-300',
  quote_portal: 'border-teal-500/20 bg-teal-500/10 text-teal-300',
}

const quickFilters = computed(() => [
  { label: 'Tudo', category: '', event_type: '' },
  { label: 'Membros', category: 'membership', event_type: '' },
  { label: 'Documentos', category: 'service_order_document', event_type: '' },
  { label: 'Financeiro', category: 'service_order_financial', event_type: '' },
  { label: 'Portal', category: 'quote_portal', event_type: '' },
])

function fmtDateTime(value: string | null | undefined) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function categoryLabel(cat: string) {
  return CATEGORY_LABELS[cat] ?? cat
}

function eventLabel(evt: string) {
  return EVENT_TYPE_LABELS[evt] ?? evt.replace(/_/g, ' ')
}

function categoryColor(cat: string) {
  return CATEGORY_COLORS[cat] ?? 'border-white/10 bg-white/5 text-stone-300'
}

function hasDiff(entry: AuditLogEntry) {
  return entry.before_values !== null || entry.after_values !== null
}

function toggleExpand(id: number) {
  expanded.value = expanded.value === id ? null : id
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await getAuditLogs({
      category: category.value || undefined,
      event_type: eventType.value || undefined,
    })
    entries.value = res.data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Não foi possível carregar o histórico.'
  } finally {
    loading.value = false
  }
}

async function applyFilter(cat: string, evt: string) {
  category.value = cat
  eventType.value = evt
  await router.push({
    name: 'audit',
    query: {
      ...(cat ? { category: cat } : {}),
      ...(evt ? { event_type: evt } : {}),
    },
  })
}

onMounted(async () => {
  if (typeof route.query.category === 'string') category.value = route.query.category
  if (typeof route.query.event_type === 'string') eventType.value = route.query.event_type
  await load()
})

watch(
  () => [route.query.category, route.query.event_type],
  async ([nextCat, nextEvt], [prevCat, prevEvt]) => {
    if (nextCat === prevCat && nextEvt === prevEvt) return
    category.value = typeof nextCat === 'string' ? nextCat : ''
    eventType.value = typeof nextEvt === 'string' ? nextEvt : ''
    await load()
  },
)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold text-white">Histórico de auditoria</h1>
      <p class="mt-1 text-sm text-stone-400">
        Registro de eventos relevantes da empresa: alterações de membros, documentos emitidos, financeiro e portal.
      </p>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <!-- Filtros rápidos -->
    <div class="flex flex-wrap items-center gap-2">
      <button
        v-for="f in quickFilters"
        :key="f.label"
        type="button"
        :class="[
          'rounded-xl border px-3 py-1.5 text-xs font-semibold transition',
          category === f.category && eventType === f.event_type
            ? 'border-amber-500/40 bg-amber-500/10 text-amber-300'
            : 'border-white/10 bg-white/5 text-stone-400 hover:border-white/20 hover:text-stone-200',
        ]"
        @click="applyFilter(f.category, f.event_type)"
      >
        {{ f.label }}
      </button>
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando histórico…</div>

    <div v-else-if="entries.length === 0" class="rounded-2xl border border-white/10 bg-white/5 px-6 py-12 text-center text-stone-500">
      Nenhum evento encontrado para os filtros selecionados.
    </div>

    <div v-else class="space-y-2">
      <article
        v-for="entry in entries"
        :key="entry.id"
        class="rounded-2xl border border-white/10 bg-white/5 transition"
      >
        <!-- Cabeçalho clicável -->
        <button
          type="button"
          class="flex w-full flex-wrap items-start gap-3 px-5 py-4 text-left"
          @click="hasDiff(entry) || entry.context ? toggleExpand(entry.id) : undefined"
        >
          <span :class="['shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold', categoryColor(entry.category)]">
            {{ categoryLabel(entry.category) }}
          </span>

          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-white">{{ eventLabel(entry.event_type) }}</p>
            <p v-if="entry.actor_name" class="mt-0.5 text-xs text-stone-500">
              por <span class="text-stone-400">{{ entry.actor_name }}</span>
            </p>
          </div>

          <time class="shrink-0 text-xs text-stone-500">{{ fmtDateTime(entry.occurred_at) }}</time>

          <svg
            v-if="hasDiff(entry) || entry.context"
            :class="['h-4 w-4 shrink-0 text-stone-500 transition-transform', expanded === entry.id ? 'rotate-180' : '']"
            viewBox="0 0 16 16"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6l4 4 4-4" />
          </svg>
        </button>

        <!-- Detalhes expandidos -->
        <div v-if="expanded === entry.id" class="border-t border-white/10 px-5 py-4 space-y-4">
          <div v-if="hasDiff(entry)">
            <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-stone-500">Alterações</p>
            <AuditDiff :before="entry.before_values" :after="entry.after_values" />
          </div>
          <div v-if="entry.context && Object.keys(entry.context).length > 0">
            <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-stone-500">Contexto</p>
            <pre class="rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-xs text-stone-300 overflow-auto">{{ JSON.stringify(entry.context, null, 2) }}</pre>
          </div>
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";
</style>
