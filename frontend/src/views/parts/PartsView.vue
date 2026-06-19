<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useTenantRouting } from '../../composables/useTenantRouting'
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
const meta = ref({ total: 0, per_page: 20, current_page: 1, last_page: 1, from: 0, to: 0 })

const canManage = computed(() => auth.permissions.includes('parts.manage'))

const categories = computed(() => {
  const all = parts.value.map((p) => p.category).filter(Boolean) as string[]
  return [...new Set(all)].sort()
})

async function load() {
  loading.value = true
  try {
    const res = await listParts({
      q: q.value || undefined,
      category: selectedCategory.value || undefined,
      low_stock: showLowStock.value || undefined,
      active: 'all',
      per_page: 20,
      page: page.value,
    })
    parts.value = res.data
    meta.value = res.meta
  } finally {
    loading.value = false
  }
}

function applyFilter() {
  page.value = 1
  load()
}

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

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold" style="color: var(--c-text)">Peças e Estoque</h1>
        <p class="mt-1 text-sm" style="color: var(--c-text-muted)">
          Controle de componentes e materiais utilizados nas ordens de serviço.
        </p>
      </div>
      <RouterLink
        v-if="canManage"
        :to="tenantRoute('parts.create')"
        class="btn-primary shrink-0"
      >
        + Nova Peça
      </RouterLink>
    </div>

    <!-- Alerta de estoque baixo -->
    <div
      v-if="lowStockCount > 0"
      class="flex items-center gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200 cursor-pointer"
      @click="showLowStock = true; applyFilter()"
    >
      <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
      </svg>
      <span><strong>{{ lowStockCount }} {{ lowStockCount === 1 ? 'peça' : 'peças' }}</strong> com estoque abaixo do mínimo. Clique para filtrar.</span>
    </div>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-3">
      <input
        v-model="q"
        type="text"
        placeholder="Buscar por nome, SKU ou categoria..."
        class="form-input flex-1 min-w-52"
        @keydown.enter="applyFilter"
        @input="applyFilter"
      />
      <select v-model="selectedCategory" class="form-input w-44" @change="applyFilter">
        <option value="">Todas as categorias</option>
        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
      </select>
      <label class="flex items-center gap-2 cursor-pointer select-none text-sm" style="color: var(--c-text-muted)">
        <input
          v-model="showLowStock"
          type="checkbox"
          class="h-4 w-4 rounded"
          @change="applyFilter"
        />
        Somente estoque baixo
      </label>
    </div>

    <!-- Tabela -->
    <div class="overflow-x-auto rounded-2xl border" style="border-color: var(--c-border)">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b text-xs uppercase tracking-wider" style="border-color: var(--c-border); color: var(--c-text-dim)">
            <th class="px-4 py-3 text-left">Peça</th>
            <th class="px-4 py-3 text-left hidden md:table-cell">SKU / Categoria</th>
            <th class="px-4 py-3 text-right">Estoque</th>
            <th class="px-4 py-3 text-right hidden sm:table-cell">Custo</th>
            <th class="px-4 py-3 text-right hidden sm:table-cell">Preço venda</th>
            <th class="px-4 py-3 text-center hidden lg:table-cell">Status</th>
            <th v-if="canManage" class="px-4 py-3 text-right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="px-4 py-10 text-center text-sm" style="color: var(--c-text-dim)">
              Carregando...
            </td>
          </tr>
          <tr v-else-if="parts.length === 0">
            <td colspan="7" class="px-4 py-10 text-center text-sm" style="color: var(--c-text-dim)">
              Nenhuma peça encontrada.
            </td>
          </tr>
          <tr
            v-for="part in parts"
            :key="part.id"
            class="border-b transition-colors"
            :class="{ 'opacity-50': !part.active }"
            style="border-color: var(--c-border)"
          >
            <!-- Nome -->
            <td class="px-4 py-3">
              <RouterLink
                :to="tenantRoute('parts.edit', { params: { id: part.id } })"
                class="font-medium hover:underline"
                style="color: var(--c-text)"
              >
                {{ part.name }}
              </RouterLink>
              <p v-if="part.description" class="mt-0.5 text-xs truncate max-w-48" style="color: var(--c-text-dim)">
                {{ part.description }}
              </p>
            </td>

            <!-- SKU / Categoria -->
            <td class="px-4 py-3 hidden md:table-cell" style="color: var(--c-text-muted)">
              <span v-if="part.sku" class="font-mono text-xs">{{ part.sku }}</span>
              <span v-else class="text-xs italic" style="color: var(--c-text-dim)">—</span>
              <p v-if="part.category" class="mt-0.5 text-xs" style="color: var(--c-text-dim)">{{ part.category }}</p>
            </td>

            <!-- Estoque -->
            <td class="px-4 py-3 text-right">
              <span
                class="font-semibold tabular-nums"
                :class="part.is_low_stock && part.active ? 'text-amber-400' : ''"
                :style="!part.is_low_stock || !part.active ? { color: 'var(--c-text)' } : {}"
              >
                {{ part.stock_quantity.toLocaleString('pt-BR') }}
              </span>
              <span class="ml-1 text-xs" style="color: var(--c-text-dim)">{{ part.unit }}</span>
              <div v-if="part.is_low_stock && part.active" class="mt-0.5 text-[11px] text-amber-500">
                mín. {{ part.min_stock_quantity }}
              </div>
            </td>

            <!-- Custo -->
            <td class="px-4 py-3 text-right hidden sm:table-cell text-xs" style="color: var(--c-text-muted)">
              {{ fmtMoney(part.cost_price_cents) }}
            </td>

            <!-- Preço venda -->
            <td class="px-4 py-3 text-right hidden sm:table-cell text-xs" style="color: var(--c-text-muted)">
              {{ fmtMoney(part.sale_price_cents) }}
            </td>

            <!-- Status -->
            <td class="px-4 py-3 text-center hidden lg:table-cell">
              <span
                class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                :class="part.active
                  ? 'bg-green-500/10 text-green-400'
                  : 'bg-stone-500/10 text-stone-400'"
              >
                {{ part.active ? 'Ativo' : 'Inativo' }}
              </span>
            </td>

            <!-- Ações -->
            <td v-if="canManage" class="px-4 py-3 text-right">
              <div class="flex justify-end gap-2">
                <RouterLink
                  :to="tenantRoute('parts.edit', { params: { id: part.id } })"
                  class="text-xs hover:underline"
                  style="color: var(--c-text-muted)"
                >
                  Editar
                </RouterLink>
                <button
                  class="text-xs hover:underline text-red-400"
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
