<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { listCustomers, type Customer } from '../../api/customers'

const customers = ref<Customer[]>([])
const loading = ref(true)
const q = ref('')
let searchTimer: ReturnType<typeof setTimeout>

async function load() {
  loading.value = true
  try {
    const res = await listCustomers(q.value || undefined)
    customers.value = res.data
  } finally {
    loading.value = false
  }
}

watch(q, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 300)
})

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-white">Clientes</h1>
      <RouterLink
        :to="{ name: 'customers.create' }"
        class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
      >
        + Novo Cliente
      </RouterLink>
    </div>

    <input
      v-model="q"
      type="search"
      placeholder="Buscar por nome, CPF/CNPJ, e-mail, telefone…"
      class="w-full max-w-md rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20"
    />

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

    <div v-else-if="customers.length === 0" class="py-16 text-center text-stone-500">
      Nenhum cliente encontrado.
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-white/10">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-white/10 bg-white/5">
            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Nome</th>
            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Tipo</th>
            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Documento</th>
            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Contato</th>
            <th class="px-5 py-3" />
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <tr
            v-for="c in customers"
            :key="c.id"
            class="transition hover:bg-white/5"
          >
            <td class="px-5 py-3.5">
              <p class="font-medium text-white">{{ c.name }}</p>
              <p v-if="c.trade_name" class="text-xs text-stone-500">{{ c.trade_name }}</p>
            </td>
            <td class="px-5 py-3.5">
              <span
                :class="c.customer_type === 'PJ' ? 'bg-sky-500/10 text-sky-300' : 'bg-violet-500/10 text-violet-300'"
                class="rounded-full px-2 py-0.5 text-xs font-semibold"
              >
                {{ c.customer_type }}
              </span>
            </td>
            <td class="px-5 py-3.5 text-stone-400">{{ c.document ?? '—' }}</td>
            <td class="px-5 py-3.5 text-stone-400">
              <span v-if="c.contacts[0]">
                {{ c.contacts[0].phone ?? c.contacts[0].email ?? '—' }}
              </span>
              <span v-else>—</span>
            </td>
            <td class="px-5 py-3.5 text-right">
              <RouterLink
                :to="{ name: 'customers.detail', params: { id: c.id } }"
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
</template>
