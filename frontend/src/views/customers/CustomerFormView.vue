<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { getCustomer, createCustomer, updateCustomer, type CustomerPayload } from '../../api/customers'
import { ApiError } from '../../api/client'
import { useTenantRouting } from '../../composables/useTenantRouting'
import { phoneMask, documentMask, cepMask } from '../../utils/masks'

const route = useRoute()
const router = useRouter()
const { tenantRoute } = useTenantRouting()

const isEdit = computed(() => !!route.params.id)
const loading = ref(false)
const saving = ref(false)
const error = ref('')

const form = ref<CustomerPayload>({
  customer_type: 'PF',
  name: '',
  trade_name: '',
  document: '',
  notes: '',
  contacts: [{ label: '', name: '', email: '', phone: '' }],
  addresses: [],
})

onMounted(async () => {
  if (!isEdit.value) return
  loading.value = true
  const res = await getCustomer(Number(route.params.id))
  const c = res.data
  form.value = {
    customer_type: c.customer_type,
    name: c.name,
    trade_name: c.trade_name ?? '',
    document: c.document ?? '',
    notes: c.notes ?? '',
    contacts: c.contacts.map((ct) => ({ label: ct.label ?? '', name: ct.name ?? '', email: ct.email ?? '', phone: ct.phone ?? '' })),
    addresses: c.addresses.map((a) => ({
      label: a.label ?? '', recipient: a.recipient ?? '', line_1: a.line_1, line_2: a.line_2 ?? '',
      district: a.district ?? '', city: a.city ?? '', state: a.state ?? '', postal_code: a.postal_code ?? '',
    })),
  }
  loading.value = false
})

function addContact() {
  form.value.contacts!.push({ label: '', name: '', email: '', phone: '' })
}

function removeContact(i: number) {
  form.value.contacts!.splice(i, 1)
}

function addAddress() {
  form.value.addresses!.push({ label: '', recipient: '', line_1: '', line_2: '', district: '', city: '', state: '', postal_code: '' })
}

function removeAddress(i: number) {
  form.value.addresses!.splice(i, 1)
}

function handleCepInput(addr: any, event: Event) {
  const input = event.target as HTMLInputElement
  addr.postal_code = cepMask(input.value)

  const digits = addr.postal_code.replace(/\D/g, '')
  if (digits.length === 8) {
    lookupCep(addr, digits)
  }
}

async function lookupCep(addr: any, cep: string) {
  try {
    const response = await fetch(`/api/v1/cep/${cep}`)
    const data = await response.json()

    if (data) {
      if (data.street) addr.line_1 = data.street
      if (data.district) addr.district = data.district
      if (data.city) addr.city = data.city
      if (data.state) addr.state = data.state
    }
  } catch (e) {
    console.error('Erro ao buscar CEP:', e)
  }
}

async function submit() {
  error.value = ''
  saving.value = true
  try {
    const payload: CustomerPayload = {
      ...form.value,
      trade_name: form.value.trade_name || null,
      document: form.value.document || null,
      notes: form.value.notes || null,
    }

    if (isEdit.value) {
      await updateCustomer(Number(route.params.id), payload)
      router.push(tenantRoute('customers.detail', { params: { id: route.params.id } }))
    } else {
      const res = await createCustomer(payload)
      router.push(tenantRoute('customers.detail', { params: { id: res.data.id } }))
    }
  } catch (e) {
    if (e instanceof ApiError) {
      error.value = (e.data as { message?: string })?.message ?? 'Erro ao salvar.'
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
    <div class="flex items-center gap-4">
      <RouterLink :to="tenantRoute('customers')" class="text-stone-500 hover:text-stone-200">← Clientes</RouterLink>
    </div>

    <h1 class="text-2xl font-semibold text-white">{{ isEdit ? 'Editar Cliente' : 'Novo Cliente' }}</h1>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

    <form v-else class="space-y-6" @submit.prevent="submit">
      <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
        {{ error }}
      </div>

      <!-- Dados principais -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Dados Gerais</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-sm text-stone-300">Tipo de Pessoa</label>
            <div class="flex gap-3">
              <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-300">
                <input v-model="form.customer_type" type="radio" value="PF" class="accent-amber-500" /> Pessoa Física
              </label>
              <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-300">
                <input v-model="form.customer_type" type="radio" value="PJ" class="accent-amber-500" /> Pessoa Jurídica
              </label>
            </div>
          </div>

          <div>
            <label class="mb-1.5 block text-sm text-stone-300">{{ form.customer_type === 'PJ' ? 'Razão Social' : 'Nome Completo' }} *</label>
            <input v-model="form.name" required class="input" />
          </div>

          <div v-if="form.customer_type === 'PJ'">
            <label class="mb-1.5 block text-sm text-stone-300">Nome Fantasia</label>
            <input v-model="form.trade_name" class="input" />
          </div>

          <div>
            <label class="mb-1.5 block text-sm text-stone-300">{{ form.customer_type === 'PJ' ? 'CNPJ' : 'CPF' }}</label>
            <input
              v-model="form.document"
              maxlength="18"
              class="input"
              @input="form.document = documentMask(form.document)"
            />
          </div>

          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-sm text-stone-300">Observações</label>
            <textarea v-model="form.notes" rows="3" class="input resize-none" />
          </div>
        </div>
      </div>

      <!-- Contatos -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <div class="mb-5 flex items-center justify-between">
          <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Contatos</h2>
          <button type="button" class="text-sm text-amber-400 hover:text-amber-300" @click="addContact">+ Adicionar</button>
        </div>
        <div class="space-y-4">
          <div
            v-for="(contact, i) in form.contacts"
            :key="i"
            class="rounded-xl border border-white/5 bg-black/20 p-4"
          >
            <div class="grid gap-3 sm:grid-cols-2">
              <input v-model="contact.label" placeholder="Rótulo (ex: Pessoal, Comercial)" class="input" />
              <input v-model="contact.name" placeholder="Nome do contato" class="input" />
              <input v-model="contact.email" type="email" placeholder="E-mail" class="input" />
              <input
                v-model="contact.phone"
                placeholder="(48) 9 8828 0387"
                maxlength="16"
                class="input"
                @input="contact.phone = phoneMask(contact.phone)"
              />
            </div>
            <button
              v-if="(form.contacts?.length ?? 0) > 1"
              type="button"
              class="mt-2 text-xs text-red-400 hover:text-red-300"
              @click="removeContact(i)"
            >
              Remover contato
            </button>
          </div>
        </div>
      </div>

      <!-- Endereços -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <div class="mb-5 flex items-center justify-between">
          <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Endereços</h2>
          <button type="button" class="text-sm text-amber-400 hover:text-amber-300" @click="addAddress">+ Adicionar</button>
        </div>
        <div class="space-y-4">
          <div
            v-for="(addr, i) in form.addresses"
            :key="i"
            class="rounded-xl border border-white/5 bg-black/20 p-4"
          >
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="relative sm:col-span-2">
                <input
                  v-model="addr.postal_code"
                  placeholder="CEP (88901-056)"
                  maxlength="9"
                  class="input"
                  @input="handleCepInput(addr, $event)"
                />
              </div>
              <input v-model="addr.label" placeholder="Rótulo (ex: Residencial, Comercial)" class="input" />
              <input v-model="addr.recipient" placeholder="Destinatário" class="input" />
              <input v-model="addr.line_1" placeholder="Endereço (logradouro e número)" class="input sm:col-span-2" required />
              <input v-model="addr.line_2" placeholder="Complemento" class="input" />
              <input v-model="addr.district" placeholder="Bairro" class="input" />
              <input v-model="addr.city" placeholder="Cidade" class="input" />
              <input v-model="addr.state" placeholder="UF" maxlength="2" class="input" />
            </div>
            <button
              type="button"
              class="mt-2 text-xs text-red-400 hover:text-red-300"
              @click="removeAddress(i)"
            >
              Remover endereço
            </button>
          </div>
        </div>
      </div>

      <div class="flex gap-3">
        <button
          type="submit"
          :disabled="saving"
          class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
        >
          {{ saving ? 'Salvando…' : 'Salvar' }}
        </button>
        <RouterLink
          :to="tenantRoute('customers')"
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
