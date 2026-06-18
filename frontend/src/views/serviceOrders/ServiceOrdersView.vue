<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { listServiceOrders, type ServiceOrder, type ServiceOrderQueue, type PaginationMeta } from '../../api/serviceOrders'
import { useSortable } from '../../composables/useSortable'
import ThSort from '../../components/ThSort.vue'
import AppPagination from '../../components/AppPagination.vue'
import { useTenantRouting } from '../../composables/useTenantRouting'

const { tenantRoute } = useTenantRouting()
const orders = ref<ServiceOrder[]>([])
const loading = ref(true)
const q = ref('')
const activeQueue = ref<ServiceOrderQueue | ''>('')
const page = ref(1)
const perPage = ref(15)
const meta = ref<PaginationMeta>({ total: 0, per_page: 15, current_page: 1, last_page: 1, from: 0, to: 0 })
let searchTimer: ReturnType<typeof setTimeout>

const queues: Array<{ key: ServiceOrderQueue | ''; label: string }> = [
  { key: '', label: 'Todas' },
  { key: 'drafts', label: 'Rascunhos' },
  { key: 'open', label: 'Abertas' },
  { key: 'pending_quote', label: 'Aguard. Orçamento' },
  { key: 'in_progress', label: 'Em Execução' },
  { key: 'ready_for_pickup', label: 'Aguard. Retirada' },
  { key: 'terminal', label: 'Finalizadas' },
]

const statusLabels: Record<string, { label: string; cls: string }> = {
  draft:            { label: 'Rascunho',       cls: 'bg-stone-500/20 text-stone-400' },
  open:             { label: 'Aberta',          cls: 'bg-sky-500/20 text-sky-300' },
  in_progress:      { label: 'Em Execução',     cls: 'bg-amber-500/20 text-amber-300' },
  completed:        { label: 'Concluída',       cls: 'bg-emerald-500/20 text-emerald-300' },
  ready_for_pickup: { label: 'Aguard. Retirada',cls: 'bg-violet-500/20 text-violet-300' },
  delivered:        { label: 'Entregue',        cls: 'bg-emerald-600/20 text-emerald-400' },
  cancelled:        { label: 'Cancelada',       cls: 'bg-red-500/20 text-red-300' },
  no_repair:        { label: 'Sem Reparo',      cls: 'bg-orange-500/20 text-orange-300' },
}

async function load() {
  loading.value = true
  try {
    const res = await listServiceOrders({
      q: q.value || undefined,
      queue: activeQueue.value || undefined,
      page: page.value,
      per_page: perPage.value,
    })
    orders.value = res.data
    meta.value = res.meta
  } finally {
    loading.value = false
  }
}

watch(q, () => {
  clearTimeout(searchTimer)
  page.value = 1
  searchTimer = setTimeout(load, 300)
})

watch(activeQueue, () => { page.value = 1; load() })
watch([page, perPage], load)

onMounted(load)

function formatDate(dt: string | null) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('pt-BR')
}

const { sorted: sortedOrders, toggleSort, sortState } = useSortable(orders, {
  reference: (os) => os.order_number ?? os.draft_reference ?? os.reference,
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-white">Ordens de Serviço</h1>
      <div class="flex items-center gap-2">
        <RouterLink
          :to="tenantRoute('service-orders.trash')"
          class="btn-ghost btn-ghost-danger"
        >
          <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0">
            <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
          </svg>
          Lixeira
        </RouterLink>
        <RouterLink
          :to="tenantRoute('service-orders.create')"
          class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
        >
          + Nova OS
        </RouterLink>
      </div>
    </div>

    <!-- Filtros de fila -->
    <div class="flex flex-wrap gap-2">
      <button
        v-for="queue in queues"
        :key="queue.key"
        :class="[
          'rounded-full px-4 py-1.5 text-sm font-medium transition',
          activeQueue === queue.key
            ? 'bg-amber-500 text-stone-950'
            : 'border border-white/10 text-stone-400 hover:border-amber-500/30 hover:text-amber-300',
        ]"
        @click="activeQueue = queue.key"
      >
        {{ queue.label }}
      </button>
    </div>

    <input
      v-model="q"
      type="search"
      placeholder="Buscar por número, cliente, equipamento, problema…"
      class="search-input"
    />

    <div v-if="loading" class="py-16 text-center td-meta">Carregando…</div>
    <div v-else-if="orders.length === 0" class="py-16 text-center td-meta">Nenhuma ordem encontrada.</div>

    <div v-else>
      <div class="t-wrap">
        <table class="w-full text-sm">
          <thead>
            <tr class="t-head">
              <ThSort :state="sortState('reference')" @click="toggleSort('reference')">OS / Ref.</ThSort>
              <ThSort :state="sortState('customer_name')" @click="toggleSort('customer_name')">Cliente / Equipamento</ThSort>
              <ThSort :state="sortState('status')" @click="toggleSort('status')">Status</ThSort>
              <ThSort :state="sortState('assigned_membership_name')" @click="toggleSort('assigned_membership_name')">Técnico</ThSort>
              <ThSort :state="sortState('opened_at')" @click="toggleSort('opened_at')">Abertura</ThSort>
              <th class="px-5 py-3" />
            </tr>
          </thead>
          <tbody class="t-body">
            <tr v-for="os in sortedOrders" :key="os.id" class="t-row">
              <td class="px-5 py-3.5">
                <p class="font-mono text-xs font-semibold text-amber-300">{{ os.order_number ?? os.draft_reference ?? os.reference }}</p>
                <p class="mt-0.5 text-xs td-meta line-clamp-1">{{ os.title }}</p>
              </td>
              <td class="px-5 py-3.5">
                <p class="text-sm td-main">{{ os.customer_name ?? '—' }}</p>
                <p class="text-xs td-sub">
                  {{ [os.equipment_brand, os.equipment_model].filter(Boolean).join(' ') || '—' }}
                </p>
              </td>
              <td class="px-5 py-3.5">
                <span
                  :class="statusLabels[os.status]?.cls ?? 'bg-stone-500/20 text-stone-400'"
                  class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                >
                  {{ statusLabels[os.status]?.label ?? os.status }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-sm td-data">{{ os.assigned_membership_name ?? '—' }}</td>
              <td class="px-5 py-3.5 text-sm td-meta">{{ formatDate(os.opened_at) }}</td>
              <td class="px-5 py-3.5 text-right">
                <RouterLink
                  :to="tenantRoute('service-orders.detail', { params: { id: os.id } })"
                  class="btn-row-action"
                >
                  <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                    <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                  </svg>
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <AppPagination
        :meta="meta"
        @update:page="page = $event"
        @update:per-page="perPage = $event; page = 1"
      />
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.search-input {
  width: 100%;
  max-width: 28rem;
  border-radius: 0.75rem;
  border: 1px solid var(--c-input-border);
  background: var(--c-input-bg);
  padding: 0.625rem 1rem;
  font-size: 0.875rem;
  color: var(--c-text);
  outline: none;
}

.search-input::placeholder {
  color: var(--c-text-dim);
}

.search-input:focus {
  border-color: rgba(251, 146, 60, 0.5);
  box-shadow: 0 0 0 1px rgba(251, 146, 60, 0.2);
}
</style>
