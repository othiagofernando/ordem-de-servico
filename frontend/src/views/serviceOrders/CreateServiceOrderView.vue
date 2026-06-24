<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { createDraft, type ServiceOrderDraftPayload } from '../../api/serviceOrders'
import { listCustomers, createCustomer, type Customer } from '../../api/customers'
import { listEquipments, createEquipment, type Equipment } from '../../api/equipments'
import { listChecklistTemplates, type ChecklistTemplate } from '../../api/checklistTemplates'
import { ApiError } from '../../api/client'
import { useTenantRouting } from '../../composables/useTenantRouting'
import { documentMask, phoneMask, stripDigits, cepMask } from '../../utils/masks'

const route = useRoute()
const router = useRouter()
const { tenantRoute } = useTenantRouting()
const saving = ref(false)
const error = ref('')
const customers = ref<Customer[]>([])
const equipments = ref<Equipment[]>([])
const checklistTemplates = ref<ChecklistTemplate[]>([])
const selectedTemplateId = ref<number | null>(null)

// Modais
const showAddCustomerModal = ref(false)
const showAddEquipmentModal = ref(false)
const addingCustomer = ref(false)
const addingEquipment = ref(false)
const customerFormError = ref('')
const equipmentFormError = ref('')

const newCustomerForm = ref({
  customer_type: 'pf' as 'pf' | 'pj',
  name: '',
  trade_name: '',
  document: '',
  notes: '',
  contacts: [{ label: 'Principal', name: '', email: '', phone: '' }],
  addresses: [],
})

const modalTab = ref<'info' | 'contacts' | 'addresses'>('info')

// Detectar automaticamente PF/PJ pelo tamanho do documento
watch(
  () => newCustomerForm.value.document,
  (doc) => {
    const digits = stripDigits(doc)
    if (digits.length >= 12) {
      newCustomerForm.value.customer_type = 'pj'
    } else if (digits.length > 0) {
      newCustomerForm.value.customer_type = 'pf'
    }
  }
)

const newEquipmentForm = ref({
  brand: '',
  model: '',
  serial_number: '',
  category: 'computer',
})

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

const filteredEquipments = computed(() => {
  return form.value.customer_id ? equipments.value.filter((e) => e.customer_id === form.value.customer_id) : []
})

watch(() => form.value.customer_id, (id) => {
  form.value.equipment_id = null
  form.value.customer_contact_id = null
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

// Auto-atualizar selectedEquipment quando equipments lista muda
watch(() => equipments.value.length, () => {
  if (form.value.equipment_id) {
    selectedEquipment.value = filteredEquipments.value.find((equipment) => equipment.id === form.value.equipment_id) ?? null
  }
})

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

function addContactToModal() {
  newCustomerForm.value.contacts.push({ label: '', name: '', email: '', phone: '' })
}

function removeContactFromModal(i: number) {
  newCustomerForm.value.contacts.splice(i, 1)
}

function addAddressToModal() {
  newCustomerForm.value.addresses.push({ label: '', recipient: '', line_1: '', line_2: '', district: '', city: '', state: '', postal_code: '' })
}

function removeAddressFromModal(i: number) {
  newCustomerForm.value.addresses.splice(i, 1)
}

async function addCustomer() {
  customerFormError.value = ''
  addingCustomer.value = true
  try {
    const res = await createCustomer({
      customer_type: newCustomerForm.value.customer_type,
      name: newCustomerForm.value.name,
      trade_name: newCustomerForm.value.trade_name || null,
      document: stripDigits(newCustomerForm.value.document) || null,
      notes: newCustomerForm.value.notes || null,
      contacts: newCustomerForm.value.contacts.map(c => ({
        label: c.label || null,
        name: c.name || null,
        email: c.email || null,
        phone: stripDigits(c.phone) || null,
      })),
      addresses: newCustomerForm.value.addresses.map(a => ({
        label: a.label || null,
        recipient: a.recipient || null,
        line_1: a.line_1 || '',
        line_2: a.line_2 || null,
        district: a.district || null,
        city: a.city || null,
        state: a.state || null,
        postal_code: stripDigits(a.postal_code) || null,
      })),
    })
    customers.value.push(res.data)
    form.value.customer_id = res.data.id
    showAddCustomerModal.value = false
    modalTab.value = 'info'
    newCustomerForm.value = {
      customer_type: 'pf',
      name: '',
      trade_name: '',
      document: '',
      notes: '',
      contacts: [{ label: 'Principal', name: '', email: '', phone: '' }],
      addresses: [],
    }
  } catch (e) {
    if (e instanceof ApiError) {
      const errors = (e.data as any)?.errors
      if (errors && typeof errors === 'object') {
        customerFormError.value = Object.values(errors).flat().join(', ')
      } else {
        customerFormError.value = (e.data as { message?: string })?.message ?? 'Erro ao criar cliente.'
      }
    } else {
      customerFormError.value = 'Erro inesperado.'
    }
  } finally {
    addingCustomer.value = false
  }
}

async function addEquipment() {
  equipmentFormError.value = ''
  addingEquipment.value = true
  try {
    const res = await createEquipment({
      ...newEquipmentForm.value,
      customer_id: form.value.customer_id!,
    })
    equipments.value.push(res.data)
    form.value.equipment_id = res.data.id
    showAddEquipmentModal.value = false
    newEquipmentForm.value = { brand: '', model: '', serial_number: '', category: 'computer' }
  } catch (e) {
    if (e instanceof ApiError) {
      equipmentFormError.value = (e.data as { message?: string })?.message ?? 'Erro ao criar equipamento.'
    } else {
      equipmentFormError.value = 'Erro inesperado.'
    }
  } finally {
    addingEquipment.value = false
  }
}

async function submit() {
  error.value = ''
  saving.value = true
  try {
    const res = await createDraft(form.value)
    router.push(tenantRoute('service-orders.detail', { params: { id: res.data.id } }))
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
    <RouterLink :to="tenantRoute('service-orders')" class="text-stone-500 hover:text-stone-200">← Ordens de Serviço</RouterLink>
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Nova Ordem de Serviço</h1>
        <p class="text-sm text-stone-400">O rascunho já nasce com contexto mínimo real: cliente, equipamento e problema relatado.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button @click="showAddCustomerModal = true" type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-stone-300 hover:border-amber-500/30 hover:text-amber-300 transition">
          + Cliente
        </button>
        <button @click="showAddEquipmentModal = true" type="button" :disabled="!form.customer_id" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-stone-300 hover:border-amber-500/30 hover:text-amber-300 transition disabled:opacity-50 disabled:cursor-not-allowed" title="Selecione um cliente primeiro">
          + Equipamento
        </button>
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
                {{ ct.name }} {{ ct.phone ? `— ${phoneMask(ct.phone)}` : '' }}
              </option>
            </select>
          </div>
        </div>
        <div v-if="selectedCustomer" class="mt-4 rounded-xl border border-white/10 bg-black/20 p-4">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Cliente selecionado</p>
          <div class="mt-2 grid gap-2 md:grid-cols-3 text-sm">
            <p class="text-stone-200">{{ selectedCustomer.name }}</p>
            <p class="text-stone-400">{{ selectedCustomer.document ? documentMask(selectedCustomer.document) : 'Sem documento' }}</p>
            <p class="text-stone-400">{{ selectedContact?.phone ? phoneMask(selectedContact.phone) : (selectedContact?.email ?? 'Sem contato principal definido') }}</p>
          </div>
        </div>
      </div>

      <!-- Equipamento -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Equipamento</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Equipamento cadastrado</label>
            <select v-model="form.equipment_id" :disabled="!form.customer_id" class="input disabled:opacity-50">
              <option :value="null">Selecione o equipamento</option>
              <option v-for="e in filteredEquipments" :key="e.id" :value="e.id">
                {{ e.brand }} {{ e.model }} {{ e.serial_number ? `— ${e.serial_number}` : '' }}
              </option>
            </select>
            <p v-if="form.customer_id && filteredEquipments.length === 0" class="mt-1 text-xs text-stone-500">
              Nenhum equipamento cadastrado para este cliente.
              <RouterLink :to="tenantRoute('equipments.create')" class="text-amber-400 hover:text-amber-300">Cadastrar</RouterLink>
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
          <div>
            <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Checklist de Recebimento <span class="text-xs text-stone-500">(opcional)</span></h2>
          </div>
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
          </div>
        </div>

        <p class="mb-4 text-sm text-stone-400">
          Use um template para acelerar ou adicione itens manualmente. Se um template for carregado, sua versão fica vinculada à O.S.
        </p>

        <p v-if="selectedEquipment && applicableChecklistTemplates.length === 0" class="mb-4 text-sm text-stone-500">
          Não há template para <span class="text-stone-300">{{ selectedEquipment.category }}</span>.
          <RouterLink :to="tenantRoute('checklists')" class="text-amber-400 hover:text-amber-300">Criar template</RouterLink>
        </p>

        <!-- Lista de items -->
        <div v-if="form.intake_checklist!.length === 0" class="rounded-lg border border-white/10 bg-white/5 p-4 text-center text-stone-400">
          <p class="text-sm">Nenhum item no checklist. Adicione itens manualmente ou carregue um template.</p>
        </div>

        <div v-else class="space-y-2 mb-4">
          <div
            v-for="(item, i) in form.intake_checklist"
            :key="i"
            class="flex items-center gap-3 rounded-lg border border-white/10 bg-white/5 p-3 hover:bg-white/10 transition"
          >
            <!-- Checkbox de status -->
            <div class="flex items-center">
              <button
                type="button"
                @click="item.checked = item.checked === true ? false : true"
                class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
                :class="
                  item.checked === true
                    ? 'border-emerald-500 bg-emerald-500/20'
                    : item.checked === false
                    ? 'border-red-500 bg-red-500/20'
                    : 'border-stone-400 hover:border-stone-300'
                "
                :title="`Status: ${item.checked === true ? 'OK' : item.checked === false ? 'Não OK' : 'Não verificado'}`"
              >
                <span v-if="item.checked === true" class="text-xs text-emerald-400">✓</span>
                <span v-else-if="item.checked === false" class="text-xs text-red-400">✕</span>
              </button>
            </div>

            <!-- Descrição do item -->
            <input
              v-model="item.label"
              type="text"
              placeholder="ex: Tela funciona? / Bateria carrega?"
              class="input flex-1 text-sm"
            />

            <!-- Notas (opcional) -->
            <input
              v-model="item.notes"
              type="text"
              placeholder="Notas..."
              class="input w-32 text-sm hidden sm:block"
            />

            <!-- Botão remover -->
            <button
              type="button"
              @click="removeChecklistItem(i)"
              class="text-stone-400 hover:text-red-400 transition"
              title="Remover item"
            >
              ✕
            </button>
          </div>
        </div>

        <!-- Botão adicionar item -->
        <button
          type="button"
          @click="addChecklistItem"
          class="w-full rounded-lg border-2 border-dashed border-amber-500/30 px-4 py-2 text-sm text-amber-400 hover:border-amber-500/50 hover:bg-amber-500/10 transition"
        >
          + Adicionar Item
        </button>

        <!-- Resumo -->
        <div v-if="form.intake_checklist && form.intake_checklist.length > 0" class="mt-4 rounded-lg border border-white/10 bg-black/20 px-4 py-3 text-xs text-stone-400">
          <div class="flex items-center justify-between">
            <span>
              {{ form.intake_checklist.length }} item(ns)
              <span v-if="form.intake_checklist_template_id" class="text-stone-500"> • template carregado</span>
            </span>
            <span v-if="form.intake_checklist.some(i => i.checked === true)" class="text-emerald-400">
              {{ form.intake_checklist.filter(i => i.checked === true).length }} OK
            </span>
            <span v-if="form.intake_checklist.some(i => i.checked === false)" class="text-red-400">
              {{ form.intake_checklist.filter(i => i.checked === false).length }} Problemas
            </span>
          </div>
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
        <RouterLink :to="tenantRoute('service-orders')" class="rounded-xl border border-white/10 px-6 py-2.5 text-sm text-stone-400 transition hover:text-stone-200">
          Cancelar
        </RouterLink>
      </div>
    </form>

    <!-- Modal: Adicionar Cliente -->
    <div v-if="showAddCustomerModal" class="fixed inset-0 flex items-center justify-center bg-black/60 p-4 z-50 overflow-y-auto">
      <div class="w-full max-w-2xl rounded-2xl bg-stone-900 shadow-xl my-8">
        <!-- Header -->
        <div class="border-b border-white/10 p-6">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-white">Novo Cliente</h2>
              <p class="mt-1 text-sm text-stone-400">Será adicionado à lista e selecionado automaticamente.</p>
            </div>
            <button type="button" @click="showAddCustomerModal = false" class="text-stone-400 hover:text-white text-xl">✕</button>
          </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-white/10 flex bg-white/5">
          <button
            type="button"
            @click="modalTab = 'info'"
            class="flex-1 px-4 py-3 text-sm font-medium transition-colors"
            :class="modalTab === 'info' ? 'border-b-2 border-amber-500 text-white' : 'text-stone-400 hover:text-stone-300'"
          >
            Dados Gerais
          </button>
          <button
            type="button"
            @click="modalTab = 'contacts'"
            class="flex-1 px-4 py-3 text-sm font-medium transition-colors"
            :class="modalTab === 'contacts' ? 'border-b-2 border-amber-500 text-white' : 'text-stone-400 hover:text-stone-300'"
          >
            Contatos
          </button>
          <button
            type="button"
            @click="modalTab = 'addresses'"
            class="flex-1 px-4 py-3 text-sm font-medium transition-colors"
            :class="modalTab === 'addresses' ? 'border-b-2 border-amber-500 text-white' : 'text-stone-400 hover:text-stone-300'"
          >
            Endereços
          </button>
        </div>

        <form @submit.prevent="addCustomer" class="p-6 space-y-4">
          <div v-if="customerFormError" class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300">
            {{ customerFormError }}
          </div>

          <!-- Aba: Dados Gerais -->
          <div v-show="modalTab === 'info'" class="space-y-4">
            <div class="flex gap-3">
              <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-300">
                <input v-model="newCustomerForm.customer_type" type="radio" value="pf" class="accent-amber-500" /> PF
              </label>
              <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-300">
                <input v-model="newCustomerForm.customer_type" type="radio" value="pj" class="accent-amber-500" /> PJ
              </label>
            </div>

            <div>
              <label class="block text-sm text-stone-300 mb-1">{{ newCustomerForm.customer_type === 'pj' ? 'Razão Social' : 'Nome Completo' }} *</label>
              <input v-model="newCustomerForm.name" type="text" class="input" required />
            </div>

            <div v-if="newCustomerForm.customer_type === 'pj'">
              <label class="block text-sm text-stone-300 mb-1">Nome Fantasia</label>
              <input v-model="newCustomerForm.trade_name" type="text" class="input" />
            </div>

            <div>
              <label class="block text-sm text-stone-300 mb-1">
                Documento ({{ newCustomerForm.customer_type === 'pf' ? 'CPF' : 'CNPJ' }})
              </label>
              <input
                v-model="newCustomerForm.document"
                type="text"
                :placeholder="newCustomerForm.customer_type === 'pf' ? '041.613.179-48' : '04.474.774/0001-79'"
                class="input"
                @input="newCustomerForm.document = documentMask(newCustomerForm.document)"
              />
            </div>

            <div>
              <label class="block text-sm text-stone-300 mb-1">Observações</label>
              <textarea v-model="newCustomerForm.notes" rows="2" class="input resize-none" />
            </div>
          </div>

          <!-- Aba: Contatos -->
          <div v-show="modalTab === 'contacts'" class="space-y-4">
            <div v-for="(contact, i) in newCustomerForm.contacts" :key="i" class="rounded-lg border border-white/10 p-4 space-y-3">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-stone-400">CONTATO {{ i + 1 }}</span>
                <button v-if="newCustomerForm.contacts.length > 1" type="button" @click="removeContactFromModal(i)" class="text-red-400 hover:text-red-300 text-xs">
                  Remover
                </button>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <input v-model="contact.label" type="text" placeholder="Label (ex: Principal)" class="input text-sm" />
                <input v-model="contact.name" type="text" placeholder="Nome" class="input text-sm" />
              </div>

              <div class="grid grid-cols-2 gap-3">
                <input v-model="contact.email" type="email" placeholder="Email" class="input text-sm" />
                <input
                  v-model="contact.phone"
                  type="tel"
                  placeholder="(48) 3521-0123"
                  class="input text-sm"
                  @input="contact.phone = phoneMask(contact.phone)"
                />
              </div>
            </div>

            <button type="button" @click="addContactToModal" class="w-full rounded-lg border border-amber-500/30 px-3 py-2 text-sm text-amber-400 hover:bg-amber-500/10">
              + Adicionar Contato
            </button>
          </div>

          <!-- Aba: Endereços -->
          <div v-show="modalTab === 'addresses'" class="space-y-4">
            <div v-if="newCustomerForm.addresses.length === 0" class="text-center py-6 text-stone-400">
              Nenhum endereço cadastrado. Adicione um se necessário.
            </div>

            <div v-for="(addr, i) in newCustomerForm.addresses" :key="i" class="rounded-lg border border-white/10 p-4 space-y-3">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-stone-400">ENDEREÇO {{ i + 1 }}</span>
                <button type="button" @click="removeAddressFromModal(i)" class="text-red-400 hover:text-red-300 text-xs">
                  Remover
                </button>
              </div>

              <input v-model="addr.label" type="text" placeholder="Label (ex: Principal)" class="input text-sm w-full" />
              <input v-model="addr.recipient" type="text" placeholder="Destinatário" class="input text-sm w-full" />
              <input v-model="addr.line_1" type="text" placeholder="Rua/Avenida *" class="input text-sm w-full" required />
              <input v-model="addr.line_2" type="text" placeholder="Complemento" class="input text-sm w-full" />

              <div class="grid grid-cols-2 gap-3">
                <input v-model="addr.district" type="text" placeholder="Bairro" class="input text-sm" />
                <input
                  v-model="addr.postal_code"
                  type="text"
                  placeholder="CEP"
                  class="input text-sm"
                  @input="addr.postal_code = cepMask(addr.postal_code)"
                />
              </div>

              <div class="grid grid-cols-2 gap-3">
                <input v-model="addr.city" type="text" placeholder="Cidade" class="input text-sm" />
                <input v-model="addr.state" type="text" placeholder="Estado" class="input text-sm" maxlength="2" />
              </div>
            </div>

            <button type="button" @click="addAddressToModal" class="w-full rounded-lg border border-amber-500/30 px-3 py-2 text-sm text-amber-400 hover:bg-amber-500/10">
              + Adicionar Endereço
            </button>
          </div>

          <!-- Botões -->
          <div class="flex gap-3 pt-4 border-t border-white/10">
            <button type="button" @click="showAddCustomerModal = false" class="flex-1 rounded-lg border border-white/10 px-4 py-2 text-stone-300 hover:border-white/20">
              Cancelar
            </button>
            <button type="submit" :disabled="!newCustomerForm.name.trim() || addingCustomer" class="flex-1 rounded-lg bg-amber-500 px-4 py-2 text-stone-950 font-semibold hover:bg-amber-400 disabled:opacity-50">
              {{ addingCustomer ? 'Criando…' : 'Adicionar Cliente' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Adicionar Equipamento -->
    <div v-if="showAddEquipmentModal" class="fixed inset-0 flex items-center justify-center bg-black/60 p-4 z-50">
      <div class="w-full max-w-md rounded-2xl bg-stone-900 p-6 shadow-xl">
        <h2 class="text-lg font-semibold text-white">Novo Equipamento</h2>
        <p class="mt-1 text-sm text-stone-400">Será adicionado à lista e selecionado automaticamente.</p>

        <div v-if="equipmentFormError" class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300">
          {{ equipmentFormError }}
        </div>

        <form @submit.prevent="addEquipment" class="mt-6 space-y-4">
          <div>
            <label class="block text-sm text-stone-300 mb-1">Marca *</label>
            <input v-model="newEquipmentForm.brand" type="text" placeholder="ex: Dell, HP, Samsung" class="input" required />
          </div>

          <div>
            <label class="block text-sm text-stone-300 mb-1">Modelo *</label>
            <input v-model="newEquipmentForm.model" type="text" placeholder="ex: Inspiron 15, LaserJet Pro" class="input" required />
          </div>

          <div>
            <label class="block text-sm text-stone-300 mb-1">Serial / Série</label>
            <input v-model="newEquipmentForm.serial_number" type="text" placeholder="Opcional" class="input" />
          </div>

          <div>
            <label class="block text-sm text-stone-300 mb-1">Categoria *</label>
            <select v-model="newEquipmentForm.category" class="input" required>
              <option value="computer">Computador</option>
              <option value="printer">Impressora</option>
              <option value="phone">Telefone</option>
              <option value="tablet">Tablet</option>
              <option value="other">Outro</option>
            </select>
          </div>

          <div class="flex gap-3 pt-2">
            <button type="button" @click="showAddEquipmentModal = false" class="flex-1 rounded-lg border border-white/10 px-4 py-2 text-stone-300 hover:border-white/20">
              Cancelar
            </button>
            <button type="submit" :disabled="!newEquipmentForm.brand.trim() || !newEquipmentForm.model.trim() || addingEquipment" class="flex-1 rounded-lg bg-amber-500 px-4 py-2 text-stone-950 font-semibold hover:bg-amber-400 disabled:opacity-50">
              {{ addingEquipment ? 'Criando…' : 'Adicionar' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

</style>
