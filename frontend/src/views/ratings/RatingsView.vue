<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useTenantRouting } from '../../composables/useTenantRouting'
import { listRatings, type Rating, type RatingsStats } from '../../api/ratings'

const { tenantRoute } = useTenantRouting()

const ratings = ref<Rating[]>([])
const stats = ref<RatingsStats | null>(null)
const loading = ref(true)
const filterRating = ref<number | ''>('')
const page = ref(1)
const meta = ref({ total: 0, per_page: 20, current_page: 1, last_page: 1, from: 0, to: 0 })

async function load() {
  loading.value = true
  try {
    const res = await listRatings({
      rating: filterRating.value !== '' ? Number(filterRating.value) : undefined,
      per_page: 20,
      page: page.value,
    })
    ratings.value = res.data
    stats.value = res.stats
    meta.value = {
      ...res.meta,
      from: res.meta.from ?? 0,
      to: res.meta.to ?? 0,
    }
  } finally {
    loading.value = false
  }
}

function applyFilter() {
  page.value = 1
  load()
}

onMounted(load)

function stars(n: number): string {
  return '★'.repeat(n) + '☆'.repeat(5 - n)
}

function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const starColor = (n: number) =>
  n >= 4 ? 'text-amber-400' : n === 3 ? 'text-yellow-500' : 'text-red-400'

const avgLabel = computed(() => {
  if (!stats.value?.average_rating) return '—'
  return stats.value.average_rating.toFixed(1)
})

const npsSegment = computed(() => {
  if (!stats.value?.average_rating) return null
  const avg = stats.value.average_rating
  if (avg >= 4.5) return { label: 'Excelente', color: 'text-emerald-400' }
  if (avg >= 3.5) return { label: 'Bom', color: 'text-amber-400' }
  if (avg >= 2.5) return { label: 'Regular', color: 'text-yellow-500' }
  return { label: 'Ruim', color: 'text-red-400' }
})

const distPercent = computed(() => {
  if (!stats.value) return {}
  const total = stats.value.total_ratings || 1
  const dist: Record<number, number> = {}
  for (let i = 1; i <= 5; i++) {
    dist[i] = Math.round(((stats.value.distribution[i] ?? 0) / total) * 100)
  }
  return dist
})
</script>

<template>
  <div class="space-y-6">
    <!-- Cabeçalho -->
    <div>
      <h1 class="text-2xl font-semibold" style="color: var(--c-text)">Avaliações dos Clientes</h1>
      <p class="mt-1 text-sm" style="color: var(--c-text-muted)">
        Feedback enviado pelos clientes após a entrega das ordens de serviço.
      </p>
    </div>

    <!-- Cards de resumo -->
    <div v-if="stats" class="grid gap-4 sm:grid-cols-3">
      <!-- Média geral -->
      <div class="rounded-2xl border p-5" style="background: var(--c-surface); border-color: var(--c-border)">
        <p class="text-xs uppercase tracking-widest" style="color: var(--c-text-muted)">Média geral</p>
        <div class="mt-2 flex items-end gap-2">
          <span class="text-4xl font-bold tabular-nums" :class="npsSegment?.color ?? 'text-amber-400'">
            {{ avgLabel }}
          </span>
          <span class="mb-0.5 text-lg text-amber-400">/ 5</span>
        </div>
        <p v-if="npsSegment" class="mt-1 text-sm font-medium" :class="npsSegment.color">
          {{ npsSegment.label }}
        </p>
      </div>

      <!-- Total de avaliações -->
      <div class="rounded-2xl border p-5" style="background: var(--c-surface); border-color: var(--c-border)">
        <p class="text-xs uppercase tracking-widest" style="color: var(--c-text-muted)">Total de avaliações</p>
        <p class="mt-2 text-4xl font-bold tabular-nums" style="color: var(--c-text)">
          {{ stats.total_ratings }}
        </p>
        <p class="mt-1 text-sm" style="color: var(--c-text-dim)">respostas recebidas</p>
      </div>

      <!-- Distribuição -->
      <div class="rounded-2xl border p-4" style="background: var(--c-surface); border-color: var(--c-border)">
        <p class="mb-2 text-xs uppercase tracking-widest" style="color: var(--c-text-muted)">Distribuição</p>
        <div class="space-y-1.5">
          <div v-for="n in [5, 4, 3, 2, 1]" :key="n" class="flex items-center gap-2">
            <span class="w-3 shrink-0 text-xs tabular-nums text-right" style="color: var(--c-text-dim)">{{ n }}</span>
            <div class="flex-1 rounded-full overflow-hidden h-2" style="background: var(--c-border)">
              <div
                class="h-2 rounded-full transition-all"
                :class="n >= 4 ? 'bg-amber-400' : n === 3 ? 'bg-yellow-500' : 'bg-red-400'"
                :style="{ width: `${distPercent[n] ?? 0}%` }"
              />
            </div>
            <span class="w-8 shrink-0 text-right text-xs tabular-nums" style="color: var(--c-text-dim)">
              {{ stats.distribution[n] ?? 0 }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-3 items-center">
      <span class="text-sm" style="color: var(--c-text-muted)">Filtrar por nota:</span>
      <button
        v-for="n in ['', 5, 4, 3, 2, 1]"
        :key="String(n)"
        class="rounded-full border px-3 py-0.5 text-xs font-medium transition"
        :class="filterRating === n
          ? 'border-amber-500/40 bg-amber-500/10 text-amber-400'
          : 'border-[var(--c-border)] text-[var(--c-text-muted)] hover:border-[var(--c-border-strong)] hover:text-[var(--c-text)]'"
        @click="filterRating = n as '' | number; applyFilter()"
      >
        {{ n === '' ? 'Todas' : `${'★'.repeat(Number(n))} ${n}` }}
      </button>
    </div>

    <!-- Lista -->
    <div v-if="loading" class="rounded-2xl border py-12 text-center text-sm" style="border-color: var(--c-border); color: var(--c-text-dim)">
      Carregando…
    </div>
    <div v-else-if="ratings.length === 0" class="rounded-2xl border py-12 text-center text-sm" style="border-color: var(--c-border); color: var(--c-text-muted)">
      Nenhuma avaliação encontrada.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="r in ratings"
        :key="r.id"
        class="rounded-2xl border px-5 py-4 space-y-2"
        style="background: var(--c-card); border-color: var(--c-border)"
      >
        <!-- Linha principal -->
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="space-y-0.5">
            <div class="flex items-center gap-2">
              <span class="text-lg font-mono leading-none" :class="starColor(r.rating)">
                {{ stars(r.rating) }}
              </span>
              <span class="text-sm font-semibold tabular-nums" :class="starColor(r.rating)">{{ r.rating }}/5</span>
            </div>
            <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-xs" style="color: var(--c-text-muted)">
              <span v-if="r.customer_name">{{ r.customer_name }}</span>
              <span v-if="r.technician_name">Técnico: {{ r.technician_name }}</span>
              <span>{{ fmtDate(r.rated_at) }}</span>
            </div>
          </div>

          <RouterLink
            :to="tenantRoute('service-orders.detail', { params: { id: r.service_order_id } })"
            class="shrink-0 text-xs hover:underline"
            style="color: var(--c-text-muted)"
          >
            {{ r.service_order_reference }}
            <span v-if="r.service_order_order_number"> · Nº {{ r.service_order_order_number }}</span>
          </RouterLink>
        </div>

        <!-- Comentário -->
        <p v-if="r.comment" class="text-sm italic" style="color: var(--c-text-muted)">
          "{{ r.comment }}"
        </p>
      </div>
    </div>

    <!-- Paginação -->
    <div v-if="meta.last_page > 1" class="flex items-center justify-between text-sm" style="color: var(--c-text-muted)">
      <span>{{ meta.from }}–{{ meta.to }} de {{ meta.total }}</span>
      <div class="flex gap-2">
        <button
          class="btn-ghost px-3 py-1.5 text-xs"
          :disabled="page <= 1"
          @click="page--; load()"
        >
          Anterior
        </button>
        <button
          class="btn-ghost px-3 py-1.5 text-xs"
          :disabled="page >= meta.last_page"
          @click="page++; load()"
        >
          Próxima
        </button>
      </div>
    </div>
  </div>
</template>
