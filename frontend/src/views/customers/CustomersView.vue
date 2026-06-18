<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { listCustomers, type Customer, type PaginationMeta } from '../../api/customers'
import { documentMask, phoneMask } from '../../utils/masks'
import { useSortable } from '../../composables/useSortable'
import { useTenantRouting } from '../../composables/useTenantRouting'
import ThSort from '../../components/ThSort.vue'
import AppPagination from '../../components/AppPagination.vue'

const customers = ref<Customer[]>([])
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
    const res = await listCustomers({ q: q.value || undefined, page: page.value, per_page: perPage.value })
    customers.value = res.data
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

const { sorted: sortedCustomers, toggleSort, sortState } = useSortable(customers, {
  contato: (c: Customer) => c.contacts[0]?.phone ?? c.contacts[0]?.email ?? '',
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-white">Clientes</h1>
      <RouterLink
        :to="tenantRoute('customers.create')"
        class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
      >
        + Novo Cliente
      </RouterLink>
    </div>

    <input
      v-model="q"
      type="search"
      placeholder="Buscar por nome, CPF/CNPJ, e-mail, telefone…"
      class="search-input"
    />

    <div v-if="loading" class="py-16 text-center td-meta">Carregando…</div>

    <div v-else-if="customers.length === 0" class="py-16 text-center td-meta">
      Nenhum cliente encontrado.
    </div>

    <div v-else>
    <div class="t-wrap">
      <table class="w-full text-sm">
        <thead>
          <tr class="t-head">
            <ThSort :state="sortState('name')" @click="toggleSort('name')">Nome</ThSort>
            <ThSort :state="sortState('customer_type')" @click="toggleSort('customer_type')">Tipo</ThSort>
            <ThSort :state="sortState('document')" @click="toggleSort('document')">Documento</ThSort>
            <ThSort :state="sortState('contato')" @click="toggleSort('contato')">Contato</ThSort>
            <th class="px-5 py-3" />
          </tr>
        </thead>
        <tbody class="t-body">
          <tr
            v-for="c in sortedCustomers"
            :key="c.id"
            class="t-row"
          >
            <td class="px-5 py-3.5">
              <p class="font-medium td-main">{{ c.name }}</p>
              <p v-if="c.trade_name" class="text-xs td-meta">{{ c.trade_name }}</p>
            </td>
            <td class="px-5 py-3.5">
              <span
                :class="c.customer_type === 'PJ' ? 'bg-sky-500/10 text-sky-300' : 'bg-violet-500/10 text-violet-300'"
                class="rounded-full px-2 py-0.5 text-xs font-semibold"
              >
                {{ c.customer_type }}
              </span>
            </td>
            <td class="px-5 py-3.5 td-data">{{ c.document ? documentMask(c.document) : '—' }}</td>
            <td class="px-5 py-3.5 td-data">
              <span v-if="c.contacts[0]">
                {{ c.contacts[0].phone ? phoneMask(c.contacts[0].phone) : (c.contacts[0].email ?? '—') }}
              </span>
              <span v-else>—</span>
            </td>
            <td class="px-5 py-3.5 text-right">
              <RouterLink
                :to="tenantRoute('customers.detail', { params: { id: c.id } })"
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
