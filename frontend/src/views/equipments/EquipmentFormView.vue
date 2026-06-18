<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { getEquipment, createEquipment, updateEquipment, type EquipmentPayload } from '../../api/equipments'
import { listCustomers, type Customer } from '../../api/customers'
import { ApiError } from '../../api/client'
import { useTenantRouting } from '../../composables/useTenantRouting'

const route = useRoute()
const router = useRouter()
const { tenantRoute } = useTenantRouting()
const saving = ref(false)
const loading = ref(false)
const error = ref('')
const customers = ref<Customer[]>([])

const isEdit = computed(() => !!route.params.id)

const form = ref<EquipmentPayload>({
  customer_id: 0,
  category: '',
  brand: '',
  model: '',
  serial_number: '',
  accessories: '',
  notes: '',
})

onMounted(async () => {
  const promises: Promise<unknown>[] = [listCustomers().then(res => { customers.value = res.data })]

  if (isEdit.value) {
    loading.value = true
    promises.push(
      getEquipment(Number(route.params.id)).then(res => {
        const e = res.data
        form.value = {
          customer_id: e.customer_id,
          category: e.category,
          brand: e.brand ?? '',
          model: e.model ?? '',
          serial_number: e.serial_number ?? '',
          accessories: e.accessories ?? '',
          notes: e.notes ?? '',
        }
      }),
    )
  }

  await Promise.all(promises)
  loading.value = false
})

async function submit() {
  error.value = ''
  saving.value = true
  try {
    const payload: EquipmentPayload = {
      ...form.value,
      brand: form.value.brand || null,
      model: form.value.model || null,
      serial_number: form.value.serial_number || null,
      accessories: form.value.accessories || null,
      notes: form.value.notes || null,
    }

    if (isEdit.value) {
      await updateEquipment(Number(route.params.id), payload)
      router.push(tenantRoute('equipments.detail', { params: { id: route.params.id } }))
    } else {
      const res = await createEquipment(payload)
      router.push(tenantRoute('equipments.detail', { params: { id: res.data.id } }))
    }
  } catch (e) {
    if (e instanceof ApiError) {
      const errData = e.data as { message?: string; errors?: Record<string, string[]> }
      const first = errData.errors ? Object.values(errData.errors)[0]?.[0] : null
      error.value = first ?? errData.message ?? 'Erro ao salvar.'
    } else {
      error.value = 'Erro inesperado.'
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <RouterLink :to="isEdit ? tenantRoute('equipments.detail', { params: { id: route.params.id } }) : tenantRoute('equipments')" class="text-stone-500 hover:text-stone-200">
      ← {{ isEdit ? 'Equipamento' : 'Equipamentos' }}
    </RouterLink>
    <h1 class="text-2xl font-semibold text-white">{{ isEdit ? 'Editar Equipamento' : 'Novo Equipamento' }}</h1>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

    <form v-else class="space-y-6" @submit.prevent="submit">
      <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
        {{ error }}
      </div>

      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-sm text-stone-300">Cliente *</label>
            <select v-model="form.customer_id" required :disabled="isEdit" class="input disabled:opacity-50">
              <option value="" disabled>Selecione o cliente</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Categoria *</label>
            <input v-model="form.category" required placeholder="ex: notebook, smartphone, desktop" class="input" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Marca</label>
            <input v-model="form.brand" class="input" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Modelo</label>
            <input v-model="form.model" class="input" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Número de Série</label>
            <input v-model="form.serial_number" class="input font-mono" />
          </div>
          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-sm text-stone-300">Acessórios</label>
            <input v-model="form.accessories" class="input" placeholder="ex: carregador, capa, cabo" />
          </div>
          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-sm text-stone-300">Observações</label>
            <textarea v-model="form.notes" rows="3" class="input resize-none" />
          </div>
        </div>
      </div>

      <div class="flex gap-3">
        <button type="submit" :disabled="saving" class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50">
          {{ saving ? 'Salvando…' : 'Salvar' }}
        </button>
        <RouterLink
          :to="isEdit ? tenantRoute('equipments.detail', { params: { id: route.params.id } }) : tenantRoute('equipments')"
          class="rounded-xl border border-white/10 px-6 py-2.5 text-sm text-stone-400 transition hover:text-stone-200"
        >
          Cancelar
        </RouterLink>
      </div>
    </form>
  </div>
</template>

<style scoped>
@reference "../../style.css";

</style>
