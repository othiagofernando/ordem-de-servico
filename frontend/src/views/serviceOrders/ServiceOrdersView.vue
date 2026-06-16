<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { listServiceOrders, type ServiceOrder, type ServiceOrderQueue } from '../../api/serviceOrders'

const route = useRoute()
const router = useRouter()
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
  { key: 'warranty_returns', label: 'Garantia' },
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

const queueSummaries: Record<string, string> = {
  '': 'Visão completa da fila visível.',
  drafts: 'Rascunhos ainda não abertos operacionalmente.',
  open: 'Ordens abertas aguardando diagnóstico, atribuição ou avanço inicial.',
  pending_quote: 'Ordens paradas por definição comercial do orçamento.',
  in_progress: 'Ordens em execução técnica.',
  ready_for_pickup: 'Ordens prontas aguardando retirada pelo cliente.',
  warranty_returns: 'Retornos vinculados à garantia e revisão de cobertura.',
  terminal: 'Ordens encerradas por entrega, cancelamento ou sem reparo.',
}

const activeQueueLabel = computed(() =>
  queues.find((queue) => queue.key === activeQueue.value)?.label ?? 'Todas',
)

const activeFilters = computed(() =>
  [
    activeQueue.value && `Fila: ${activeQueueLabel.value}`,
    q.value.trim() && `Busca: ${q.value.trim()}`,
  ].filter(Boolean) as string[],
)

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

function normalizedQueue(value: unknown): ServiceOrderQueue | '' {
  if (
    value === 'drafts' ||
    value === 'open' ||
    value === 'pending_quote' ||
    value === 'in_progress' ||
    value === 'ready_for_pickup' ||
    value === 'terminal' ||
    value === 'warranty_returns'
  ) {
    return value
  }

  return ''
}

function syncStateFromRoute() {
  q.value = typeof route.query.q === 'string' ? route.query.q : ''
  activeQueue.value = normalizedQueue(route.query.queue)
}

function syncRouteQuery(nextQueue: ServiceOrderQueue | '', nextSearch: string) {
  const queue = nextQueue || undefined
  const search = nextSearch.trim() || undefined

  if (route.query.queue === queue && route.query.q === search) {
    return
  }

  router.replace({
    name: 'service-orders',
    query: {
      ...route.query,
      queue,
      q: search,
    },
  })
}

watch(q, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => syncRouteQuery(activeQueue.value, q.value), 300)
})

watch(activeQueue, (value) => syncRouteQuery(value, q.value))

watch(() => [route.query.queue, route.query.q], () => {
  syncStateFromRoute()
  load()
}, { immediate: true })

onMounted(syncStateFromRoute)

function formatDate(dt: string | null) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('pt-BR')
}

function clearFilters() {
  q.value = ''
  activeQueue.value = ''
}

function rowSignals(order: ServiceOrder) {
  const signals: Array<{ label: string; cls: string }> = []

  if (order.status === 'open' && !order.diagnosis_completed_at) {
    signals.push({
      label: 'Diagnóstico pendente',
      cls: 'bg-sky-500/10 text-sky-300',
    })
  }

  if (order.status === 'open' && order.diagnosis_completed_at && !order.assigned_membership_id) {
    signals.push({
      label: 'Atribuir técnico',
      cls: 'bg-sky-500/10 text-sky-300',
    })
  }

  if (
    order.status === 'open'
    && order.diagnosis_completed_at
    && order.assigned_membership_id
    && order.latest_quote_status !== 'approved'
  ) {
    signals.push({
      label: 'Aprovar orçamento',
      cls: 'bg-amber-500/10 text-amber-300',
    })
  }

  if (order.status === 'ready_for_pickup') {
    signals.push({
      label: 'Conferir saldo e entrega',
      cls: 'bg-emerald-500/10 text-emerald-300',
    })
  }

  if (order.warranty_origin_service_order_id) {
    signals.push({
      label: 'Garantia',
      cls: 'bg-violet-500/10 text-violet-300',
    })
  }

  return signals
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Ordens de Serviço</h1>
        <p class="mt-1 text-sm text-stone-400">{{ queueSummaries[activeQueue] ?? queueSummaries[''] }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <RouterLink
          :to="{ name: 'service-orders.create' }"
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

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <input
        v-model="q"
        type="search"
        placeholder="Buscar por número, cliente, equipamento, problema…"
        class="w-full max-w-md rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20"
      />
      <button
        v-if="activeFilters.length > 0"
        class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-stone-300 hover:border-white/20 hover:text-white"
        @click="clearFilters"
      >
        Limpar filtros
      </button>
    </div>

    <div v-if="activeFilters.length > 0" class="flex flex-wrap gap-2">
      <span
        v-for="filter in activeFilters"
        :key="filter"
        class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-stone-300"
      >
        {{ filter }}
      </span>
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>
    <div v-else-if="orders.length === 0" class="rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-stone-400">
      <p>Nenhuma ordem encontrada para os filtros atuais.</p>
      <RouterLink :to="{ name: 'service-orders.create' }" class="mt-4 inline-flex rounded-lg border border-amber-500/30 px-3 py-1.5 text-xs text-amber-300 hover:bg-amber-500/10">
        Abrir nova O.S.
      </RouterLink>
    </div>

    <div v-else>
      <p class="mb-3 text-sm text-stone-500">{{ total }} ordem(ns) encontrada(s) em {{ activeQueueLabel.toLowerCase() }}.</p>
      <div class="overflow-hidden rounded-2xl border border-white/10">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-white/10 bg-white/5">
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">OS / Ref.</th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Cliente / Equipamento</th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Status</th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Sinais</th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Técnico</th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Abertura</th>
              <th class="px-5 py-3" />
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5">
            <tr v-for="os in orders" :key="os.id" class="transition hover:bg-white/5">
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
              <td class="px-5 py-3.5">
                <div class="flex flex-wrap gap-1.5">
                  <span
                    v-for="signal in rowSignals(os)"
                    :key="signal.label"
                    :class="signal.cls"
                    class="rounded-full px-2 py-0.5 text-xs"
                  >
                    {{ signal.label }}
                  </span>
                </div>
              </td>
              <td class="px-5 py-3.5 text-sm text-stone-400">{{ os.assigned_membership_name ?? '—' }}</td>
              <td class="px-5 py-3.5 text-sm text-stone-500">{{ formatDate(os.opened_at) }}</td>
              <td class="px-5 py-3.5 text-right">
                <RouterLink
                  :to="{ name: 'service-orders.detail', params: { id: os.id } }"
                  class="rounded-lg border border-white/10 px-3 py-1 text-xs text-stone-400 transition hover:border-amber-500/30 hover:text-amber-300"
                >
                  Ver
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
