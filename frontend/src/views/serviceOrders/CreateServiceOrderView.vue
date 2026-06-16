<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { createDraft, type ServiceOrderDraftPayload } from '../../api/serviceOrders'
import { listCustomers, type Customer } from '../../api/customers'
import { listEquipments, type Equipment } from '../../api/equipments'
import { listChecklistTemplates, type ChecklistTemplate } from '../../api/checklistTemplates'
import { ApiError } from '../../api/client'

const route = useRoute()
const router = useRouter()
const saving = ref(false)
const error = ref('')
const customers = ref<Customer[]>([])
const equipments = ref<Equipment[]>([])
const checklistTemplates = ref<ChecklistTemplate[]>([])
const selectedTemplateId = ref<number | null>(null)

const form = ref<ServiceOrderDraftPayload & { customer_id: number | null; equipment_id: number | null }>({
  customer_id: null,
  customer_contact_id: null,
  equipment_id: null,
  intake_checklist_template_id: null,
  equipment_description: '',
  reported_problem: '',
  intake_checklist: [],
})

onMounted(async () => {
  const [custRes, eqRes, tplRes] = await Promise.all([listCustomers(), listEquipments(), listChecklistTemplates()])
  customers.value = custRes.data
  equipments.value = eqRes.data
  checklistTemplates.value = tplRes.data

  const customerId = typeof route.query.customer_id === 'string' ? Number(route.query.customer_id) : null
  const equipmentId = typeof route.query.equipment_id === 'string' ? Number(route.query.equipment_id) : null

  if (customerId && customers.value.some((customer) => customer.id === customerId)) {
    form.value.customer_id = customerId
  }

  if (equipmentId && equipments.value.some((equipment) => equipment.id === equipmentId)) {
    const equipment = equipments.value.find((item) => item.id === equipmentId) ?? null
    if (equipment) {
      form.value.customer_id = equipment.customer_id
      form.value.equipment_id = equipment.id
      form.value.equipment_description = [equipment.brand, equipment.model, equipment.serial_number].filter(Boolean).join(' / ')
    }
  }
})

const filteredEquipments = ref<Equipment[]>([])

watch(() => form.value.customer_id, (id) => {
  form.value.equipment_id = null
  form.value.customer_contact_id = null
  filteredEquipments.value = id ? equipments.value.filter((e) => e.customer_id === id) : []
})

const selectedCustomer = ref<Customer | null>(null)
watch(() => form.value.customer_id, (id) => {
  selectedCustomer.value = customers.value.find((c) => c.id === id) ?? null
})

const selectedEquipment = ref<Equipment | null>(null)
watch(() => form.value.equipment_id, (id) => {
  selectedEquipment.value = filteredEquipments.value.find((equipment) => equipment.id === id) ?? null
  selectedTemplateId.value = null
  form.value.intake_checklist_template_id = null
}, { immediate: true })

const applicableChecklistTemplates = computed(() =>
  selectedEquipment.value
    ? checklistTemplates.value.filter((template) => template.category === selectedEquipment.value?.category)
    : checklistTemplates.value,
)

const selectedContact = computed(() =>
  selectedCustomer.value?.contacts.find((contact) => contact.id === form.value.customer_contact_id) ?? null,
)

const readinessItems = computed(() => [
  { label: 'Cliente selecionado', done: !!form.value.customer_id },
  { label: 'Equipamento definido', done: !!form.value.equipment_id || !!form.value.equipment_description?.trim() },
  { label: 'Problema relatado preenchido', done: !!form.value.reported_problem?.trim() },
])

const canSubmit = computed(() =>
  !!form.value.customer_id &&
  (!!form.value.equipment_id || !!form.value.equipment_description?.trim()) &&
  !!form.value.reported_problem?.trim(),
)

function addChecklistItem() {
  form.value.intake_checklist!.push({ label: '', checked: null, notes: null })
}

function removeChecklistItem(i: number) {
  form.value.intake_checklist!.splice(i, 1)
}

function loadTemplate() {
  const tpl = checklistTemplates.value.find((t) => t.id === selectedTemplateId.value)
  if (!tpl) return
  form.value.intake_checklist_template_id = tpl.id
  form.value.intake_checklist = tpl.items.map((item) => ({
    label: item.label,
    checked: null,
    notes: null,
  }))
}

async function submit() {
  error.value = ''
  saving.value = true
  try {
    const res = await createDraft(form.value)
    router.push({ name: 'service-orders.detail', params: { id: res.data.id } })
  } catch (e) {
    if (e instanceof ApiError) {
      error.value = (e.data as { message?: string })?.message ?? 'Erro ao criar rascunho.'
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
    <RouterLink :to="{ name: 'service-orders' }" class="text-stone-500 hover:text-stone-200">← Ordens de Serviço</RouterLink>
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Nova Ordem de Serviço</h1>
        <p class="text-sm text-stone-400">O rascunho já nasce com contexto mínimo real: cliente, equipamento e problema relatado.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <RouterLink :to="{ name: 'customers.create' }" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-stone-300 hover:border-amber-500/30 hover:text-amber-300">
          + Cliente
        </RouterLink>
        <RouterLink :to="{ name: 'equipments.create' }" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-stone-300 hover:border-amber-500/30 hover:text-amber-300">
          + Equipamento
        </RouterLink>
      </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
      <div
        v-for="item in readinessItems"
        :key="item.label"
        class="rounded-2xl border p-4"
        :class="item.done ? 'border-emerald-500/20 bg-emerald-500/5' : 'border-white/10 bg-white/5'"
      >
        <p class="text-xs font-semibold uppercase tracking-widest" :class="item.done ? 'text-emerald-300' : 'text-stone-500'">
          {{ item.done ? 'Pronto' : 'Pendente' }}
        </p>
        <p class="mt-2 text-sm" :class="item.done ? 'text-white' : 'text-stone-300'">{{ item.label }}</p>
      </div>
    </div>

    <form class="space-y-6" @submit.prevent="submit">
      <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
        {{ error }}
      </div>

      <!-- Cliente e contato -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Cliente</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-sm text-stone-300">Cliente</label>
            <select v-model="form.customer_id" class="input">
              <option :value="null">Selecione o cliente</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <div v-if="selectedCustomer && selectedCustomer.contacts.length > 0">
            <label class="mb-1.5 block text-sm text-stone-300">Contato</label>
            <select v-model="form.customer_contact_id" class="input">
              <option :value="null">Selecione o contato</option>
              <option v-for="ct in selectedCustomer.contacts" :key="ct.id" :value="ct.id">
                {{ ct.name }} {{ ct.phone ? `— ${ct.phone}` : '' }}
              </option>
            </select>
          </div>
        </div>
        <div v-if="selectedCustomer" class="mt-4 rounded-xl border border-white/10 bg-black/20 p-4">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Cliente selecionado</p>
          <div class="mt-2 grid gap-2 md:grid-cols-3 text-sm">
            <p class="text-stone-200">{{ selectedCustomer.name }}</p>
            <p class="text-stone-400">{{ selectedCustomer.document ?? 'Sem documento' }}</p>
            <p class="text-stone-400">{{ selectedContact?.phone ?? selectedContact?.email ?? 'Sem contato principal definido' }}</p>
          </div>
        </div>
      </div>

      <!-- Equipamento -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Equipamento</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Equipamento cadastrado</label>
            <select v-model="form.equipment_id" :disabled="!form.customer_id" class="input disabled:opacity-40">
              <option :value="null">Selecione o equipamento</option>
              <option v-for="e in filteredEquipments" :key="e.id" :value="e.id">
                {{ e.brand }} {{ e.model }} {{ e.serial_number ? `— ${e.serial_number}` : '' }}
              </option>
            </select>
            <p v-if="form.customer_id && filteredEquipments.length === 0" class="mt-1 text-xs text-stone-500">
              Nenhum equipamento cadastrado para este cliente.
              <RouterLink :to="{ name: 'equipments.create' }" class="text-amber-400 hover:text-amber-300">Cadastrar</RouterLink>
            </p>
          </div>
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Descrição do Equipamento</label>
            <input v-model="form.equipment_description" placeholder="ex: Dell Inspiron 15 / SN: ABC123" class="input" />
          </div>
        </div>
        <div v-if="selectedEquipment?.category" class="mt-3 rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-stone-300">
          Categoria do equipamento: <span class="font-medium text-white">{{ selectedEquipment.category }}</span>
          <span class="mx-2 text-stone-600">•</span>
          <span>{{ selectedEquipment.brand ?? 'Sem marca' }} {{ selectedEquipment.model ?? '' }}</span>
        </div>
      </div>

      <!-- Problema -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Problema Relatado</h2>
        <textarea
          v-model="form.reported_problem"
          rows="4"
          placeholder="Descreva o problema relatado pelo cliente…"
          class="input resize-none"
        />
        <p class="mt-2 text-xs text-stone-500">Descreva o sintoma informado pelo cliente, contexto de uso e qualquer detalhe observado no recebimento.</p>
      </div>

      <!-- Checklist de recebimento -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Checklist de Recebimento</h2>
          <div class="flex flex-wrap items-center gap-2">
            <template v-if="applicableChecklistTemplates.length > 0">
              <select v-model="selectedTemplateId" class="input w-auto text-xs">
                <option :value="null">Selecionar template…</option>
                <option v-for="t in applicableChecklistTemplates" :key="t.id" :value="t.id">
                  {{ t.category }} — {{ t.title }} (v{{ t.version }})
                </option>
              </select>
              <button
                type="button"
                :disabled="!selectedTemplateId"
                class="rounded-lg border border-amber-500/30 px-3 py-1.5 text-xs text-amber-400 hover:bg-amber-500/10 disabled:opacity-40"
                @click="loadTemplate"
              >
                Carregar
              </button>
            </template>
            <button type="button" class="text-sm text-amber-400 hover:text-amber-300" @click="addChecklistItem">+ Item</button>
          </div>
        </div>
        <p class="mb-4 text-sm text-stone-400">
          Use um template para acelerar o recebimento. Se um template for carregado, sua versão fica vinculada à O.S. para preservar o snapshot usado no intake.
        </p>
        <p v-if="selectedEquipment && applicableChecklistTemplates.length === 0" class="mb-4 text-sm text-stone-500">
          Não há template cadastrado para a categoria <span class="text-stone-300">{{ selectedEquipment.category }}</span>.
          <RouterLink :to="{ name: 'checklists' }" class="text-amber-400 hover:text-amber-300">Criar template</RouterLink>
        </p>
        <div v-if="form.intake_checklist!.length === 0" class="text-sm text-stone-500">
          Nenhum item adicionado. O checklist é opcional no rascunho.
        </div>
        <div class="space-y-2">
          <div
            v-for="(item, i) in form.intake_checklist"
            :key="i"
            class="flex items-center gap-3"
          >
            <input v-model="item.label" placeholder="Descreva o item" class="input flex-1" />
            <select v-model="item.checked" class="input w-28">
              <option :value="null">—</option>
              <option :value="true">OK</option>
              <option :value="false">Não OK</option>
            </select>
            <button type="button" class="text-red-400 hover:text-red-300" @click="removeChecklistItem(i)">✕</button>
          </div>
        </div>
        <div v-if="form.intake_checklist && form.intake_checklist.length > 0" class="mt-4 rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-xs text-stone-400">
          {{ form.intake_checklist.length }} item(ns) no checklist atual
          <span v-if="form.intake_checklist_template_id"> • template vinculado à versão carregada</span>
        </div>
      </div>

      <div class="flex gap-3">
        <button
          type="submit"
          :disabled="saving || !canSubmit"
          class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
        >
          {{ saving ? 'Criando…' : 'Criar Rascunho' }}
        </button>
        <RouterLink :to="{ name: 'service-orders' }" class="rounded-xl border border-white/10 px-6 py-2.5 text-sm text-stone-400 transition hover:text-stone-200">
          Cancelar
        </RouterLink>
      </div>
    </form>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.input {
  @apply w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20;
}
</style>
