<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { listServiceOrders, type ServiceOrder, type ServiceOrderQueue } from '../../api/serviceOrders'
import { useSortable } from '../../composables/useSortable'
import ThSort from '../../components/ThSort.vue'

const route = useRoute()
const orders = ref<ServiceOrder[]>([])
const total = ref(0)
const loading = ref(true)
const q = ref('')
const activeQueue = ref<ServiceOrderQueue | ''>('')
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
    })
    orders.value = res.data
    total.value = res.meta.total
  } finally {
    loading.value = false
  }
}

watch(q, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 300)
})

watch(activeQueue, load)

onMounted(() => {
  if (route.query.status) {
    // handled by status filter in queue
  }
  load()
})

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
      <RouterLink
        :to="{ name: 'service-orders.create' }"
        class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
      >
        + Nova OS
      </RouterLink>
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
      class="w-full max-w-md rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20"
    />

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>
    <div v-else-if="orders.length === 0" class="py-16 text-center text-stone-500">Nenhuma ordem encontrada.</div>

    <div v-else>
      <p class="mb-3 text-sm text-stone-500">{{ total }} ordem(ns) encontrada(s)</p>
      <div class="overflow-hidden rounded-2xl border border-white/10">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-white/10 bg-white/5">
              <ThSort :state="sortState('reference')" @click="toggleSort('reference')">OS / Ref.</ThSort>
              <ThSort :state="sortState('customer_name')" @click="toggleSort('customer_name')">Cliente / Equipamento</ThSort>
              <ThSort :state="sortState('status')" @click="toggleSort('status')">Status</ThSort>
              <ThSort :state="sortState('assigned_membership_name')" @click="toggleSort('assigned_membership_name')">Técnico</ThSort>
              <ThSort :state="sortState('opened_at')" @click="toggleSort('opened_at')">Abertura</ThSort>
              <th class="px-5 py-3" />
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5">
            <tr v-for="os in sortedOrders" :key="os.id" class="transition hover:bg-white/5">
              <td class="px-5 py-3.5">
                <p class="font-mono text-xs font-semibold text-amber-300">{{ os.order_number ?? os.draft_reference ?? os.reference }}</p>
                <p class="mt-0.5 text-xs text-stone-500 line-clamp-1">{{ os.title }}</p>
              </td>
              <td class="px-5 py-3.5">
                <p class="text-sm text-stone-200">{{ os.customer_name ?? '—' }}</p>
                <p class="text-xs text-stone-500">
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
              <td class="px-5 py-3.5 text-sm text-stone-400">{{ os.assigned_membership_name ?? '—' }}</td>
              <td class="px-5 py-3.5 text-sm text-stone-500">{{ formatDate(os.opened_at) }}</td>
              <td class="px-5 py-3.5 text-right">
                <RouterLink
                  :to="{ name: 'service-orders.detail', params: { id: os.id } }"
                  class="inline-flex items-center justify-center rounded-lg border border-white/10 p-1.5 text-stone-500 transition hover:border-amber-500/30 hover:text-amber-300"
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
    </div>
  </div>
</template>
