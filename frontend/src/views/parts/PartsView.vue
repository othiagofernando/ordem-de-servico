<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useTenantRouting } from '../../composables/useTenantRouting'
import { useSortable } from '../../composables/useSortable'
import ThSort from '../../components/ThSort.vue'
import AppPagination from '../../components/AppPagination.vue'
import { listParts, deletePart, type Part } from '../../api/parts'
import { useAuthStore } from '../../stores/auth'

const { tenantRoute } = useTenantRouting()
const auth = useAuthStore()

const parts = ref<Part[]>([])
const loading = ref(true)
const q = ref('')
const selectedCategory = ref('')
const showLowStock = ref(false)
const page = ref(1)
const perPage = ref(15)
const meta = ref({ total: 0, per_page: 15, current_page: 1, last_page: 1, from: 0, to: 0 })
let searchTimer: ReturnType<typeof setTimeout>

const canManage = computed(() => auth.permissions.includes('parts.manage'))

const { sorted: sortedParts, toggleSort, sortState } = useSortable(parts, {
  name: (p: Part) => p.name,
  category: (p: Part) => p.category ?? '',
  stock_quantity: (p: Part) => p.stock_quantity,
  cost_price_cents: (p: Part) => p.cost_price_cents,
  sale_price_cents: (p: Part) => p.sale_price_cents,
})

async function load() {
  loading.value = true
  try {
    const res = await listParts({
      q: q.value || undefined,
      category: selectedCategory.value || undefined,
      low_stock: showLowStock.value || undefined,
      per_page: perPage.value,
      page: page.value,
    })
    parts.value = res.data
    meta.value = { ...res.meta }
  } finally {
    loading.value = false
  }
}

watch(q, () => {
  clearTimeout(searchTimer)
  page.value = 1
  searchTimer = setTimeout(load, 300)
})

watch([selectedCategory, showLowStock], () => { page.value = 1; load() })
watch([page, perPage], load)

onMounted(load)

async function remove(part: Part) {
  if (!confirm(`Excluir "${part.name}"? Esta ação não pode ser desfeita.`)) return
  try {
    await deletePart(part.id)
    parts.value = parts.value.filter((p) => p.id !== part.id)
    meta.value.total--
  } catch (e: unknown) {
    const msg = (e as { data?: { message?: string } })?.data?.message
    alert(msg ?? 'Não foi possível excluir a peça.')
  }
}

function fmtMoney(cents: number): string {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

const lowStockCount = computed(() => parts.value.filter((p) => p.is_low_stock && p.active).length)

const categories = computed(() => {
  const all = parts.value.map((p) => p.category).filter(Boolean) as string[]
  return [...new Set(all)].sort()
})
</script>

<template>
  <div class="space-y-6">

    <!-- Cabeçalho -->
    <div class="flex items-center justify-between gap-4">
      <h1 class="text-2xl font-semibold text-white">Peças e Estoque</h1>
      <RouterLink
        v-if="canManage"
        :to="tenantRoute('parts.create')"
        class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
      >
        + Nova Peça
      </RouterLink>
    </div>

    <!-- Alerta de estoque baixo -->
    <div
      v-if="lowStockCount > 0"
      class="flex cursor-pointer items-center gap-3 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-300 transition hover:bg-amber-500/15"
      @click="showLowStock = true"
    >
      <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
      </svg>
      <span>
        <strong>{{ lowStockCount }} {{ lowStockCount === 1 ? 'peça' : 'peças' }}</strong>
        com estoque abaixo do mínimo. Clique para filtrar.
      </span>
    </div>

    <!-- Filtros -->
    <div class="flex flex-wrap items-center gap-3">
      <input
        v-model="q"
        type="search"
        placeholder="Buscar por nome, SKU ou categoria…"
        class="search-input"
      />

      <select v-model="selectedCategory" class="filter-select">
        <option value="">Todas as categorias</option>
        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
      </select>

      <button
        :class="[
          'rounded-full px-4 py-1.5 text-sm font-medium transition',
          showLowStock
            ? 'bg-amber-500 text-stone-950'
            : 'border border-white/10 text-stone-400 hover:border-amber-500/30 hover:text-amber-300',
        ]"
        @click="showLowStock = !showLowStock"
      >
        Estoque baixo
      </button>
    </div>

    <!-- Loading / Vazio -->
    <div v-if="loading" class="py-16 text-center td-meta">Carregando…</div>
    <div v-else-if="parts.length === 0" class="py-16 text-center td-meta">Nenhuma peça encontrada.</div>

    <!-- Tabela -->
    <div v-else>
      <div class="t-wrap">
        <table class="w-full text-sm">
          <thead>
            <tr class="t-head">
              <ThSort :state="sortState('name')" @click="toggleSort('name')">Peça</ThSort>
              <ThSort :state="sortState('category')" @click="toggleSort('category')" class="hidden md:table-cell">SKU / Categoria</ThSort>
              <ThSort :state="sortState('stock_quantity')" @click="toggleSort('stock_quantity')">Estoque</ThSort>
              <ThSort :state="sortState('cost_price_cents')" @click="toggleSort('cost_price_cents')" class="hidden sm:table-cell">Custo</ThSort>
              <ThSort :state="sortState('sale_price_cents')" @click="toggleSort('sale_price_cents')" class="hidden sm:table-cell">Preço venda</ThSort>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest td-meta hidden lg:table-cell">Status</th>
              <th v-if="canManage" class="px-5 py-3" />
            </tr>
          </thead>
          <tbody class="t-body">
            <tr v-for="part in sortedParts" :key="part.id" class="t-row" :class="{ 'opacity-50': !part.active }">
              <!-- Nome -->
              <td class="px-5 py-3.5">
                <RouterLink
                  :to="tenantRoute('parts.edit', { params: { id: part.id } })"
                  class="font-medium td-main hover:text-amber-300 transition-colors"
                >
                  {{ part.name }}
                </RouterLink>
                <p v-if="part.description" class="mt-0.5 text-xs td-meta line-clamp-1">{{ part.description }}</p>
              </td>

              <!-- SKU / Categoria -->
              <td class="px-5 py-3.5 hidden md:table-cell">
                <p v-if="part.sku" class="font-mono text-xs td-data">{{ part.sku }}</p>
                <p v-else class="text-xs td-meta italic">—</p>
                <p v-if="part.category" class="mt-0.5 text-xs td-meta">{{ part.category }}</p>
              </td>

              <!-- Estoque -->
              <td class="px-5 py-3.5 whitespace-nowrap">
                <span
                  class="font-semibold tabular-nums"
                  :class="part.is_low_stock && part.active ? 'text-amber-400' : 'td-main'"
                >
                  {{ part.stock_quantity.toLocaleString('pt-BR') }}
                </span>
                <span class="ml-1 text-xs td-meta">{{ part.unit }}</span>
                <p v-if="part.is_low_stock && part.active" class="mt-0.5 text-xs text-amber-500">
                  mín. {{ part.min_stock_quantity }}
                </p>
              </td>

              <!-- Custo -->
              <td class="px-5 py-3.5 text-xs td-data hidden sm:table-cell whitespace-nowrap">
                {{ fmtMoney(part.cost_price_cents) }}
              </td>

              <!-- Preço venda -->
              <td class="px-5 py-3.5 text-xs td-data hidden sm:table-cell whitespace-nowrap">
                {{ fmtMoney(part.sale_price_cents) }}
              </td>

              <!-- Status -->
              <td class="px-5 py-3.5 hidden lg:table-cell">
                <span
                  class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="part.active
                    ? 'bg-emerald-500/20 text-emerald-300'
                    : 'bg-stone-500/20 text-stone-400'"
                >
                  {{ part.active ? 'Ativo' : 'Inativo' }}
                </span>
              </td>

              <!-- Ações -->
              <td v-if="canManage" class="px-5 py-3.5 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-2">
                  <RouterLink
                    :to="tenantRoute('parts.edit', { params: { id: part.id } })"
                    class="link-row-action"
                  >
                    Editar
                  </RouterLink>
                  <button
                    class="link-row-action hover:!border-red-500/30 hover:!text-red-400"
                    @click="remove(part)"
                  >
                    Excluir
                  </button>
                </div>
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

.search-input {
  width: 100%;
  max-width: 22rem;
  border-radius: 0.75rem;
  border: 1px solid var(--c-input-border);
  background: var(--c-input-bg);
  padding: 0.625rem 1rem;
  font-size: 0.875rem;
  color: var(--c-text);
  outline: none;
}
.search-input::placeholder { color: var(--c-text-dim); }
.search-input:focus {
  border-color: rgba(251, 146, 60, 0.5);
  box-shadow: 0 0 0 1px rgba(251, 146, 60, 0.2);
}

.filter-select {
  border-radius: 0.75rem;
  border: 1px solid var(--c-input-border);
  background: var(--c-input-bg);
  padding: 0.5rem 0.875rem;
  font-size: 0.875rem;
  color: var(--c-text-muted);
  outline: none;
  transition: border-color 0.15s;
}
.filter-select:focus { border-color: rgba(251, 146, 60, 0.4); }
</style>
