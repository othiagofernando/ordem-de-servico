<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ApiError } from '../../api/client'
import {
  createAdminCompany,
  getAdminCompany,
  updateAdminCompany,
  type AdminCompanyUpsertPayload,
} from '../../api/admin'
import { phoneMask, documentMask, cepMask } from '../../utils/masks'

const route = useRoute()
const router = useRouter()
const isEdit = computed(() => Boolean(route.params.id))
const loading = ref(false)
const saving = ref(false)
const error = ref('')

const form = reactive<AdminCompanyUpsertPayload>({
  name: '',
  trade_name: '',
  document: '',
  email: '',
  phone: '',
  website: '',
  address_line_1: '',
  address_line_2: '',
  district: '',
  city: '',
  state: '',
  postal_code: '',
  status: 'active',
  is_active: true,
  passwordless_code_enabled: true,
  magic_link_enabled: true,
})

const companyId = computed(() => (isEdit.value ? String(route.params.id) : null))
const cepLoading = ref(false)
const statusLabel = computed(() => {
  switch (form.status) {
    case 'active':
      return 'Ativa'
    case 'suspended':
      return 'Suspensa'
    case 'cancelled':
      return 'Cancelada'
    default:
      return form.status
  }
})
const completedFields = computed(() => {
  const fields = [
    form.name,
    form.document,
    form.email,
    form.phone,
    form.address_line_1,
    form.city,
    form.state,
    form.postal_code,
  ]

  return fields.filter((value) => String(value ?? '').trim().length > 0).length
})
const completionLabel = computed(() => `${completedFields.value}/8 campos principais`)
const accessModesEnabled = computed(() => [form.passwordless_code_enabled, form.magic_link_enabled].filter(Boolean).length)

onMounted(async () => {
  if (!isEdit.value) return

  loading.value = true
  try {
    const response = await getAdminCompany(Number(route.params.id))
    const company = response.data

    form.name = company.name
    form.trade_name = company.trade_name ?? ''
    form.document = company.document ?? ''
    form.email = company.email ?? ''
    form.phone = company.phone ?? ''
    form.website = company.website ?? ''
    form.address_line_1 = company.address_line_1 ?? ''
    form.address_line_2 = company.address_line_2 ?? ''
    form.district = company.district ?? ''
    form.city = company.city ?? ''
    form.state = company.state ?? ''
    form.postal_code = company.postal_code ?? ''
    form.status = company.status
    form.is_active = company.is_active
    form.passwordless_code_enabled = company.passwordless_code_enabled ?? true
    form.magic_link_enabled = company.magic_link_enabled ?? true
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao carregar empresa.'
  } finally {
    loading.value = false
  }
})

async function submit() {
  saving.value = true
  error.value = ''

  try {
    const payload: AdminCompanyUpsertPayload = {
      ...form,
      trade_name: form.trade_name || null,
      document: form.document || null,
      email: form.email || null,
      phone: form.phone || null,
      website: form.website || null,
      address_line_1: form.address_line_1 || null,
      address_line_2: form.address_line_2 || null,
      district: form.district || null,
      city: form.city || null,
      state: form.state || null,
      postal_code: form.postal_code || null,
    }

    if (isEdit.value) {
      await updateAdminCompany(Number(route.params.id), payload)
      await router.push({ name: 'admin.company', params: { id: route.params.id } })
    } else {
      const response = await createAdminCompany(payload)
      await router.push({ name: 'admin.company', params: { id: response.data.id } })
    }
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao salvar empresa.'
  } finally {
    saving.value = false
  }
}

function handleCepInput(event: Event) {
  const input = event.target as HTMLInputElement
  form.postal_code = cepMask(input.value)

  const digits = form.postal_code.replace(/\D/g, '')
  if (digits.length === 8) {
    lookupCep(digits)
  }
}

async function lookupCep(cep: string) {
  cepLoading.value = true
  try {
    const response = await fetch(`/api/v1/cep/${cep}`)
    const data = await response.json()

    if (data) {
      if (data.street) form.address_line_1 = data.street
      if (data.district) form.district = data.district
      if (data.city) form.city = data.city
      if (data.state) form.state = data.state
    }
  } catch (e) {
    console.error('Erro ao buscar CEP:', e)
  } finally {
    cepLoading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <RouterLink :to="{ name: 'admin.companies' }" class="text-sm text-stone-500 hover:text-stone-300">← Empresas</RouterLink>
      <RouterLink
        v-if="isEdit"
        :to="{ name: 'admin.company', params: { id: route.params.id } }"
        class="text-sm text-stone-500 hover:text-stone-300"
      >
        Voltar aos detalhes
      </RouterLink>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando empresa…</div>

    <form v-else class="mx-auto grid max-w-7xl gap-6 xl:grid-cols-[minmax(0,1fr)_320px]" @submit.prevent="submit">
      <div class="space-y-6">
        <section class="overflow-hidden rounded-[2rem] border border-white/10 bg-white/5">
          <div class="border-b border-white/10 bg-gradient-to-r from-amber-500/10 via-transparent to-sky-500/10 px-6 py-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-300">
                    {{ isEdit ? 'Edição' : 'Cadastro' }}
                  </span>
                  <span
                    v-if="companyId"
                    class="rounded-full border border-white/10 bg-black/20 px-2.5 py-1 text-[11px] font-medium text-stone-400"
                  >
                    Empresa #{{ companyId }}
                  </span>
                </div>
                <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">
                  {{ isEdit ? 'Editar Empresa' : 'Nova Empresa' }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-stone-400">
                  {{ isEdit ? 'Atualize os dados cadastrais e os controles de acesso da empresa sem perder o contexto operacional.' : 'Cadastre uma nova empresa diretamente pelo super-admin, já deixando a base pronta para assinatura e operação.' }}
                </p>
              </div>

              <div class="grid min-w-[240px] gap-3 sm:grid-cols-2 xl:grid-cols-1">
                <div class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
                  <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Status atual</p>
                  <p class="mt-2 text-sm font-semibold text-white">{{ statusLabel }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
                  <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Preenchimento</p>
                  <p class="mt-2 text-sm font-semibold text-white">{{ completionLabel }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="grid gap-4 px-6 py-5 md:grid-cols-3">
            <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
              <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Identificação</p>
              <p class="mt-2 text-sm text-stone-300">Nome, documento e canais principais da empresa.</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
              <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Endereço</p>
              <p class="mt-2 text-sm text-stone-300">Base física e dados de localização para operação e faturamento.</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
              <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Acesso</p>
              <p class="mt-2 text-sm text-stone-300">Status operacional e métodos de autenticação habilitados.</p>
            </div>
          </div>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Identificação</h2>
              <p class="mt-1 text-sm text-stone-500">Informações institucionais usadas em login, contato e reconhecimento da empresa.</p>
            </div>
            <span class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-[11px] font-medium text-stone-400">Obrigatório no onboarding</span>
          </div>
          <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
              <label class="mb-1.5 block text-sm text-stone-300">Nome da empresa *</label>
              <input v-model="form.name" required class="input" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">Nome fantasia</label>
              <input v-model="form.trade_name" class="input" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">Documento</label>
              <input
                v-model="form.document"
                maxlength="18"
                class="input"
                @input="form.document = documentMask(form.document)"
              />
            </div>
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">E-mail</label>
              <input v-model="form.email" type="email" class="input" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">Telefone</label>
              <input
                v-model="form.phone"
                maxlength="16"
                placeholder="(48) 9 8828 0387"
                class="input"
                @input="form.phone = phoneMask(form.phone)"
              />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1.5 block text-sm text-stone-300">Website</label>
              <input v-model="form.website" class="input" placeholder="https://empresa.test" />
            </div>
          </div>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
          <div>
            <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Endereço</h2>
            <p class="mt-1 text-sm text-stone-500">Dados úteis para contratos, comunicação operacional e referência geográfica.</p>
          </div>
          <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">CEP</label>
              <div class="relative">
                <input
                  v-model="form.postal_code"
                  placeholder="88901-056"
                  maxlength="9"
                  :disabled="cepLoading"
                  class="input"
                  @input="handleCepInput"
                />
                <div v-if="cepLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
                  <div class="h-4 w-4 animate-spin rounded-full border-2 border-stone-500 border-t-amber-500"></div>
                </div>
              </div>
            </div>
            <div></div>
            <div class="md:col-span-2">
              <label class="mb-1.5 block text-sm text-stone-300">Endereço</label>
              <input v-model="form.address_line_1" class="input" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">Complemento</label>
              <input v-model="form.address_line_2" class="input" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">Bairro</label>
              <input v-model="form.district" class="input" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">Cidade</label>
              <input v-model="form.city" class="input" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">UF</label>
              <input v-model="form.state" maxlength="2" class="input" />
            </div>
          </div>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
          <div>
            <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Acesso</h2>
            <p class="mt-1 text-sm text-stone-500">Defina se a empresa pode operar agora e quais mecanismos de autenticação ficam disponíveis.</p>
          </div>
          <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">Status</label>
              <select v-model="form.status" class="input">
                <option value="active">Ativa</option>
                <option value="suspended">Suspensa</option>
                <option value="cancelled">Cancelada</option>
              </select>
            </div>
            <div class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
              <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Operação</p>
              <label class="mt-2 flex items-center gap-3 text-sm text-stone-300">
                <input v-model="form.is_active" type="checkbox" class="rounded border-white/20 bg-transparent" />
                Empresa operacionalmente ativa
              </label>
            </div>
            <label class="flex items-center gap-3 rounded-2xl border border-white/10 bg-black/20 px-4 py-4 text-sm text-stone-300">
              <input v-model="form.passwordless_code_enabled" type="checkbox" class="rounded border-white/20 bg-transparent" />
              <span>
                <span class="block font-medium text-stone-200">Login por código</span>
                <span class="mt-1 block text-xs text-stone-500">Permite acesso por código temporário.</span>
              </span>
            </label>
            <label class="flex items-center gap-3 rounded-2xl border border-white/10 bg-black/20 px-4 py-4 text-sm text-stone-300">
              <input v-model="form.magic_link_enabled" type="checkbox" class="rounded border-white/20 bg-transparent" />
              <span>
                <span class="block font-medium text-stone-200">Magic link</span>
                <span class="mt-1 block text-xs text-stone-500">Permite autenticação por link seguro enviado ao usuário.</span>
              </span>
            </label>
          </div>
        </section>

        <div class="flex flex-wrap gap-3">
          <button
            type="submit"
            :disabled="saving"
            class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
          >
            {{ saving ? 'Salvando…' : isEdit ? 'Salvar alterações' : 'Criar empresa' }}
          </button>
          <RouterLink
            :to="isEdit ? { name: 'admin.company', params: { id: route.params.id } } : { name: 'admin.companies' }"
            class="rounded-xl border border-white/10 px-6 py-2.5 text-sm text-stone-400 transition hover:text-stone-200"
          >
            Cancelar
          </RouterLink>
        </div>
      </div>

      <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
        <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
          <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Visão rápida</h2>
          <div class="mt-5 space-y-3">
            <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
              <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Empresa</p>
              <p class="mt-2 text-sm font-semibold text-white">{{ form.name || 'Sem nome definido' }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
              <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Status</p>
              <p class="mt-2 text-sm font-semibold text-white">{{ statusLabel }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
              <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Acessos ativos</p>
              <p class="mt-2 text-sm font-semibold text-white">{{ accessModesEnabled }}/2 habilitados</p>
            </div>
          </div>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
          <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Checklist</h2>
          <div class="mt-5 space-y-3 text-sm">
            <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
              <span class="text-stone-300">Identificação principal</span>
              <span class="text-xs font-semibold" :class="form.name && form.document ? 'text-emerald-300' : 'text-stone-500'">
                {{ form.name && form.document ? 'OK' : 'Pendente' }}
              </span>
            </div>
            <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
              <span class="text-stone-300">Contato operacional</span>
              <span class="text-xs font-semibold" :class="form.email || form.phone ? 'text-emerald-300' : 'text-stone-500'">
                {{ form.email || form.phone ? 'OK' : 'Pendente' }}
              </span>
            </div>
            <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
              <span class="text-stone-300">Localização base</span>
              <span class="text-xs font-semibold" :class="form.city && form.state ? 'text-emerald-300' : 'text-stone-500'">
                {{ form.city && form.state ? 'OK' : 'Pendente' }}
              </span>
            </div>
            <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
              <span class="text-stone-300">Acesso configurado</span>
              <span class="text-xs font-semibold" :class="form.is_active ? 'text-emerald-300' : 'text-amber-300'">
                {{ form.is_active ? 'Liberado' : 'Inativo' }}
              </span>
            </div>
          </div>
        </section>
      </aside>
    </form>
  </div>
</template>

<style scoped>
@reference "../../style.css";
</style>
