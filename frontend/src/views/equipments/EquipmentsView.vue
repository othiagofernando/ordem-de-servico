<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { listEquipments, type Equipment, type PaginationMeta } from '../../api/equipments'
import { useSortable } from '../../composables/useSortable'
import { useTenantRouting } from '../../composables/useTenantRouting'
import ThSort from '../../components/ThSort.vue'
import AppPagination from '../../components/AppPagination.vue'

const equipments = ref<Equipment[]>([])
const loading = ref(true)
const q = ref('')
const page = ref(1)
const perPage = ref(15)
const meta = ref<PaginationMeta>({ total: 0, per_page: 15, current_page: 1, last_page: 1, from: 0, to: 0 })
let searchTimer: ReturnType<typeof setTimeout>
const { tenantRoute } = useTenantRouting()

async function load() {
  loading.value = true
  try {
    const res = await listEquipments({ q: q.value || undefined, page: page.value, per_page: perPage.value })
    equipments.value = res.data
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
watch([page, perPage], load)

onMounted(load)

const { sorted: sortedEquipments, toggleSort, sortState } = useSortable(equipments, {
  equipamento: (e: Equipment) => `${e.brand ?? ''} ${e.model ?? ''}`.trim(),
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-white">Equipamentos</h1>
      <RouterLink
        :to="tenantRoute('equipments.create')"
        class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
      >
        + Novo Equipamento
      </RouterLink>
    </div>

    <input
      v-model="q"
      type="search"
      placeholder="Buscar por categoria, marca, modelo, número de série, cliente…"
      class="search-input"
    />

    <div v-if="loading" class="py-16 text-center td-meta">Carregando…</div>
    <div v-else-if="equipments.length === 0" class="py-16 text-center td-meta">Nenhum equipamento encontrado.</div>

    <div v-else>
    <div class="t-wrap">
      <table class="w-full text-sm">
        <thead>
          <tr class="t-head">
            <ThSort :state="sortState('equipamento')" @click="toggleSort('equipamento')">Equipamento</ThSort>
            <ThSort :state="sortState('customer_name')" @click="toggleSort('customer_name')">Cliente</ThSort>
            <ThSort :state="sortState('serial_number')" @click="toggleSort('serial_number')">Nº de Série</ThSort>
            <th class="px-5 py-3" />
          </tr>
        </thead>
        <tbody class="t-body">
          <tr v-for="e in sortedEquipments" :key="e.id" class="t-row">
            <td class="px-5 py-3.5">
              <p class="font-medium td-main">{{ e.brand }} {{ e.model }}</p>
              <p class="text-xs td-meta capitalize">{{ e.category }}</p>
            </td>
            <td class="px-5 py-3.5 td-data">{{ e.customer_name ?? '—' }}</td>
            <td class="px-5 py-3.5 font-mono text-xs td-data">{{ e.serial_number ?? '—' }}</td>
            <td class="px-5 py-3.5 text-right">
              <RouterLink
                :to="tenantRoute('equipments.detail', { params: { id: e.id } })"
                class="link-row-action"
              >
                Ver
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
