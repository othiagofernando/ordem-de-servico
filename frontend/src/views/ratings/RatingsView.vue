<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useTenantRouting } from '../../composables/useTenantRouting'
import { useSortable } from '../../composables/useSortable'
import ThSort from '../../components/ThSort.vue'
import AppPagination from '../../components/AppPagination.vue'
import { listRatings, type Rating, type RatingsStats } from '../../api/ratings'

const { tenantRoute } = useTenantRouting()

const ratings = ref<Rating[]>([])
const stats = ref<RatingsStats | null>(null)
const loading = ref(true)
const filterStar = ref<number | ''>('')
const page = ref(1)
const perPage = ref(15)
const meta = ref({ total: 0, per_page: 15, current_page: 1, last_page: 1, from: 0, to: 0 })

async function load() {
  loading.value = true
  try {
    const res = await listRatings({
      rating: filterStar.value !== '' ? Number(filterStar.value) : undefined,
      per_page: perPage.value,
      page: page.value,
    })
    ratings.value = res.data
    stats.value = res.stats
    meta.value = { ...res.meta, from: res.meta.from ?? 0, to: res.meta.to ?? 0 }
  } finally {
    loading.value = false
  }
}

watch(filterStar, () => { page.value = 1; load() })
watch([page, perPage], load)

onMounted(load)

const { sorted: sortedRatings, toggleSort, sortState } = useSortable(ratings, {
  rating: (r: Rating) => r.rating,
  customer_name: (r: Rating) => r.customer_name ?? '',
  technician_name: (r: Rating) => r.technician_name ?? '',
  rated_at: (r: Rating) => r.rated_at,
})

function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function starDots(n: number): string {
  return '★'.repeat(n) + '☆'.repeat(5 - n)
}

const starCls = (n: number) =>
  n >= 4 ? 'text-amber-400' : n === 3 ? 'text-yellow-500' : 'text-red-400'

const avgLabel = computed(() =>
  stats.value?.average_rating ? stats.value.average_rating.toFixed(1) : '—',
)

const avgSegment = computed(() => {
  const avg = stats.value?.average_rating
  if (!avg) return null
  if (avg >= 4.5) return { label: 'Excelente', cls: 'text-emerald-400' }
  if (avg >= 3.5) return { label: 'Bom', cls: 'text-amber-400' }
  if (avg >= 2.5) return { label: 'Regular', cls: 'text-yellow-500' }
  return { label: 'Ruim', cls: 'text-red-400' }
})

const distPercent = computed(() => {
  const total = stats.value?.total_ratings || 1
  const out: Record<number, number> = {}
  for (let i = 1; i <= 5; i++) {
    out[i] = Math.round(((stats.value?.distribution[i] ?? 0) / total) * 100)
  }
  return out
})
</script>

<template>
  <div class="space-y-6">

    <!-- Cabeçalho -->
    <h1 class="text-2xl font-semibold text-white">Avaliações dos Clientes</h1>

    <!-- Cards de resumo -->
    <div v-if="stats" class="grid gap-4 sm:grid-cols-3">
      <!-- Média -->
      <div class="stat-card">
        <p class="stat-label">Média geral</p>
        <div class="flex items-end gap-2 mt-2">
          <span class="text-4xl font-bold tabular-nums" :class="avgSegment?.cls ?? 'text-amber-400'">
            {{ avgLabel }}
          </span>
          <span class="mb-1 text-lg text-amber-400/60">/ 5</span>
        </div>
        <p v-if="avgSegment" class="mt-1 text-sm font-medium" :class="avgSegment.cls">
          {{ avgSegment.label }}
        </p>
        <p v-else class="mt-1 text-sm td-meta">Sem avaliações ainda</p>
      </div>

      <!-- Total -->
      <div class="stat-card">
        <p class="stat-label">Total de avaliações</p>
        <p class="text-4xl font-bold tabular-nums text-white mt-2">{{ stats.total_ratings }}</p>
        <p class="mt-1 text-sm td-meta">respostas recebidas</p>
      </div>

      <!-- Distribuição -->
      <div class="stat-card">
        <p class="stat-label mb-3">Distribuição</p>
        <div class="space-y-2">
          <div v-for="n in [5, 4, 3, 2, 1]" :key="n" class="flex items-center gap-2">
            <span class="w-3 text-right text-xs tabular-nums shrink-0 td-meta">{{ n }}</span>
            <div class="flex-1 h-1.5 rounded-full overflow-hidden" style="background: var(--c-border-strong)">
              <div
                class="h-1.5 rounded-full transition-all duration-300"
                :class="n >= 4 ? 'bg-amber-400' : n === 3 ? 'bg-yellow-500' : 'bg-red-400'"
                :style="{ width: `${distPercent[n]}%` }"
              />
            </div>
            <span class="w-6 text-right text-xs tabular-nums shrink-0 td-meta">
              {{ stats.distribution[n] ?? 0 }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Filtro por estrela -->
    <div class="flex flex-wrap gap-2">
      <button
        v-for="opt in [{ key: '' as const, label: 'Todas' }, { key: 5, label: '★★★★★ 5' }, { key: 4, label: '★★★★☆ 4' }, { key: 3, label: '★★★☆☆ 3' }, { key: 2, label: '★★☆☆☆ 2' }, { key: 1, label: '★☆☆☆☆ 1' }]"
        :key="String(opt.key)"
        :class="[
          'rounded-full px-4 py-1.5 text-sm font-medium transition',
          filterStar === opt.key
            ? 'bg-amber-500 text-stone-950'
            : 'border border-white/10 text-stone-400 hover:border-amber-500/30 hover:text-amber-300',
        ]"
        @click="filterStar = opt.key"
      >
        {{ opt.label }}
      </button>
    </div>

    <!-- Estados vazios / loading -->
    <div v-if="loading" class="py-16 text-center td-meta">Carregando…</div>
    <div v-else-if="ratings.length === 0" class="py-16 text-center td-meta">
      Nenhuma avaliação encontrada.
    </div>

    <!-- Tabela -->
    <div v-else>
      <div class="t-wrap">
        <table class="w-full text-sm">
          <thead>
            <tr class="t-head">
              <ThSort :state="sortState('rating')" @click="toggleSort('rating')">Avaliação</ThSort>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest td-meta">O.S.</th>
              <ThSort :state="sortState('customer_name')" @click="toggleSort('customer_name')">Cliente</ThSort>
              <ThSort :state="sortState('technician_name')" @click="toggleSort('technician_name')">Técnico</ThSort>
              <ThSort :state="sortState('rated_at')" @click="toggleSort('rated_at')">Data</ThSort>
              <th class="px-5 py-3" />
            </tr>
          </thead>
          <tbody class="t-body">
            <tr v-for="r in sortedRatings" :key="r.id" class="t-row">
              <!-- Avaliação -->
              <td class="px-5 py-3.5 whitespace-nowrap">
                <span class="font-mono text-base leading-none" :class="starCls(r.rating)">
                  {{ starDots(r.rating) }}
                </span>
                <p v-if="r.comment" class="mt-1 max-w-xs truncate text-xs td-meta italic">
                  "{{ r.comment }}"
                </p>
              </td>
              <!-- O.S. -->
              <td class="px-5 py-3.5">
                <p class="font-mono text-xs font-semibold text-amber-300">
                  {{ r.service_order_order_number ?? r.service_order_reference ?? '—' }}
                </p>
                <p v-if="r.service_order_title" class="mt-0.5 text-xs td-meta line-clamp-1">
                  {{ r.service_order_title }}
                </p>
              </td>
              <!-- Cliente -->
              <td class="px-5 py-3.5 td-main">{{ r.customer_name ?? '—' }}</td>
              <!-- Técnico -->
              <td class="px-5 py-3.5 td-data">{{ r.technician_name ?? '—' }}</td>
              <!-- Data -->
              <td class="px-5 py-3.5 td-meta">{{ fmtDate(r.rated_at) }}</td>
              <!-- Ação -->
              <td class="px-5 py-3.5 text-right">
                <RouterLink
                  :to="tenantRoute('service-orders.detail', { params: { id: r.service_order_id } })"
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
        :per-page-options="[15, 30, 50]"
        @update:page="page = $event"
        @update:per-page="perPage = $event; page = 1"
      />
    </div>

  </div>
</template>

<style scoped>
@reference "../../style.css";

.stat-card {
  @apply rounded-2xl border p-5;
  background: var(--c-surface);
  border-color: var(--c-border);
}

.stat-label {
  @apply text-xs font-semibold uppercase tracking-widest;
  color: var(--c-text-muted);
}
</style>
