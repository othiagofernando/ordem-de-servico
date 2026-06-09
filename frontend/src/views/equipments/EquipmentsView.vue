<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { listEquipments, type Equipment } from '../../api/equipments'

const equipments = ref<Equipment[]>([])
const loading = ref(true)
const q = ref('')
let searchTimer: ReturnType<typeof setTimeout>

async function load() {
  loading.value = true
  try {
    const res = await listEquipments(q.value || undefined)
    equipments.value = res.data
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
      <h1 class="text-2xl font-semibold text-white">Equipamentos</h1>
      <RouterLink
        :to="{ name: 'equipments.create' }"
        class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
      >
        + Novo Equipamento
      </RouterLink>
    </div>

    <input
      v-model="q"
      type="search"
      placeholder="Buscar por categoria, marca, modelo, número de série, cliente…"
      class="w-full max-w-md rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20"
    />

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>
    <div v-else-if="equipments.length === 0" class="py-16 text-center text-stone-500">Nenhum equipamento encontrado.</div>

    <div v-else class="overflow-hidden rounded-2xl border border-white/10">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-white/10 bg-white/5">
            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Equipamento</th>
            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Cliente</th>
            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">Nº de Série</th>
            <th class="px-5 py-3" />
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <tr v-for="e in equipments" :key="e.id" class="transition hover:bg-white/5">
            <td class="px-5 py-3.5">
              <p class="font-medium text-white">{{ e.brand }} {{ e.model }}</p>
              <p class="text-xs text-stone-500 capitalize">{{ e.category }}</p>
            </td>
            <td class="px-5 py-3.5 text-stone-400">{{ e.customer_name ?? '—' }}</td>
            <td class="px-5 py-3.5 font-mono text-xs text-stone-400">{{ e.serial_number ?? '—' }}</td>
            <td class="px-5 py-3.5 text-right">
              <RouterLink
                :to="{ name: 'equipments.detail', params: { id: e.id } }"
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
