<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { getEquipment, type Equipment } from '../../api/equipments'

const route = useRoute()
const equipment = ref<Equipment | null>(null)
const loading = ref(true)

onMounted(async () => {
  const res = await getEquipment(Number(route.params.id))
  equipment.value = res.data
  loading.value = false
})
</script>

<template>
  <div class="space-y-6">
    <RouterLink :to="{ name: 'equipments' }" class="text-stone-500 hover:text-stone-200">← Equipamentos</RouterLink>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

    <template v-else-if="equipment">
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-white">{{ equipment.brand }} {{ equipment.model }}</h1>
          <p class="capitalize text-stone-400">{{ equipment.category }}</p>
        </div>
        <RouterLink
          :to="{ name: 'equipments.edit', params: { id: equipment.id } }"
          class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400"
        >
          Editar
        </RouterLink>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Identificação</h2>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-stone-500">Nº de Série</dt>
              <dd class="font-mono text-sm text-stone-200">{{ equipment.serial_number ?? '—' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-stone-500">Cliente</dt>
              <dd>
                <RouterLink
                  v-if="equipment.customer_id"
                  :to="{ name: 'customers.detail', params: { id: equipment.customer_id } }"
                  class="text-sm text-amber-400 hover:text-amber-300"
                >
                  {{ equipment.customer_name ?? equipment.customer_id }}
                </RouterLink>
                <span v-else class="text-sm text-stone-500">—</span>
              </dd>
            </div>
          </dl>
        </div>

        <div v-if="equipment.accessories || equipment.notes" class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Observações</h2>
          <p v-if="equipment.accessories" class="mb-2 text-sm text-stone-300">
            <span class="text-stone-500">Acessórios:</span> {{ equipment.accessories }}
          </p>
          <p v-if="equipment.notes" class="text-sm text-stone-300">{{ equipment.notes }}</p>
        </div>

        <div v-if="equipment.serial_overrides.length > 0" class="rounded-2xl border border-white/10 bg-white/5 p-6 lg:col-span-2">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Histórico de Série</h2>
          <div class="space-y-2">
            <div
              v-for="o in equipment.serial_overrides"
              :key="o.id"
              class="rounded-xl border border-white/5 bg-black/20 px-4 py-3"
            >
              <p class="font-mono text-sm text-stone-200">{{ o.serial_number }}</p>
              <p v-if="o.reason" class="text-xs text-stone-500">{{ o.reason }}</p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
