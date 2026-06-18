<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import {
  getSettings,
  updateSettings,
  uploadCompanyLogo,
  type CompanySettings,
} from '../../api/settings'
import { ApiError } from '../../api/client'
import { useTenantRouting } from '../../composables/useTenantRouting'

const auth = useAuthStore()
const { tenantRoute } = useTenantRouting()
const loading = ref(true)
const saving = ref(false)
const uploadingLogo = ref(false)
const error = ref('')
const success = ref(false)
const settings = ref<CompanySettings | null>(null)
const logoPreview = ref<string | null>(null)

const form = ref({
  name: '',
  slug: '',
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
  brand_name: '',
  customer_contact_line: '',
  service_order_reference_prefix: 'OS',
  portal_intro_text: '',
  portal_footer_text: '',
  portal_approved_text: '',
  portal_rejected_text: '',
  document_footer_text: '',
  default_quote_validity_days: 7,
  intake_checklists_enabled: true,
  currency: 'BRL',
  block_delivery_with_outstanding_balance: true,
  notify_quote_available: true,
  notify_quote_available_text: '',
  notify_quote_expiring: true,
  notify_quote_expiring_text: '',
  notify_ready_for_pickup: true,
  notify_ready_for_pickup_text: '',
  notify_payment_receipt: true,
  notify_payment_receipt_text: '',
  notification_footer_text: '',
  passwordless_code_enabled: false,
  magic_link_enabled: false,
})

function applySettings(data: CompanySettings) {
  settings.value = data
  logoPreview.value = data.profile.logo_url
  form.value = {
    name: data.profile.name,
    slug: data.profile.slug,
    trade_name: data.profile.trade_name ?? '',
    document: data.profile.document ?? '',
    email: data.profile.email ?? '',
    phone: data.profile.phone ?? '',
    website: data.profile.website ?? '',
    address_line_1: data.profile.address.line_1 ?? '',
    address_line_2: data.profile.address.line_2 ?? '',
    district: data.profile.address.district ?? '',
    city: data.profile.address.city ?? '',
    state: data.profile.address.state ?? '',
    postal_code: data.profile.address.postal_code ?? '',
    brand_name: data.branding.brand_name ?? '',
    customer_contact_line: data.branding.customer_contact_line ?? '',
    service_order_reference_prefix: data.service_orders.reference_prefix,
    portal_intro_text: data.portal.intro_text ?? '',
    portal_footer_text: data.portal.footer_text ?? '',
    portal_approved_text: data.portal.approved_text ?? '',
    portal_rejected_text: data.portal.rejected_text ?? '',
    document_footer_text: data.documents.footer_text ?? '',
    default_quote_validity_days: data.quotes.default_validity_days,
    intake_checklists_enabled: data.intake.checklists_enabled,
    currency: data.finance.currency,
    block_delivery_with_outstanding_balance: data.finance.block_delivery_with_outstanding_balance,
    notify_quote_available: data.notifications.quote_available,
    notify_quote_available_text: data.notifications.quote_available_text ?? '',
    notify_quote_expiring: data.notifications.quote_expiring,
    notify_quote_expiring_text: data.notifications.quote_expiring_text ?? '',
    notify_ready_for_pickup: data.notifications.ready_for_pickup,
    notify_ready_for_pickup_text: data.notifications.ready_for_pickup_text ?? '',
    notify_payment_receipt: data.notifications.payment_receipt,
    notify_payment_receipt_text: data.notifications.payment_receipt_text ?? '',
    notification_footer_text: data.communications.notification_footer_text ?? '',
    passwordless_code_enabled: data.auth.passwordless_code_enabled,
    magic_link_enabled: data.auth.magic_link_enabled,
  }
}

onMounted(async () => {
  try {
    const res = await getSettings()
    applySettings(res.data)
  } catch {
    error.value = 'Não foi possível carregar as configurações.'
  } finally {
    loading.value = false
  }
})

async function save() {
  error.value = ''
  success.value = false
  saving.value = true

  try {
    const res = await updateSettings({
      name: form.value.name,
      slug: form.value.slug,
      trade_name: form.value.trade_name || null,
      document: form.value.document || null,
      email: form.value.email || null,
      phone: form.value.phone || null,
      website: form.value.website || null,
      address_line_1: form.value.address_line_1 || null,
      address_line_2: form.value.address_line_2 || null,
      district: form.value.district || null,
      city: form.value.city || null,
      state: form.value.state || null,
      postal_code: form.value.postal_code || null,
      brand_name: form.value.brand_name || null,
      customer_contact_line: form.value.customer_contact_line || null,
      service_order_reference_prefix: form.value.service_order_reference_prefix || null,
      portal_intro_text: form.value.portal_intro_text || null,
      portal_footer_text: form.value.portal_footer_text || null,
      portal_approved_text: form.value.portal_approved_text || null,
      portal_rejected_text: form.value.portal_rejected_text || null,
      document_footer_text: form.value.document_footer_text || null,
      default_quote_validity_days: form.value.default_quote_validity_days,
      intake_checklists_enabled: form.value.intake_checklists_enabled,
      currency: form.value.currency,
      block_delivery_with_outstanding_balance: form.value.block_delivery_with_outstanding_balance,
      notify_quote_available: form.value.notify_quote_available,
      notify_quote_available_text: form.value.notify_quote_available_text || null,
      notify_quote_expiring: form.value.notify_quote_expiring,
      notify_quote_expiring_text: form.value.notify_quote_expiring_text || null,
      notify_ready_for_pickup: form.value.notify_ready_for_pickup,
      notify_ready_for_pickup_text: form.value.notify_ready_for_pickup_text || null,
      notify_payment_receipt: form.value.notify_payment_receipt,
      notify_payment_receipt_text: form.value.notify_payment_receipt_text || null,
      notification_footer_text: form.value.notification_footer_text || null,
      passwordless_code_enabled: form.value.passwordless_code_enabled,
      magic_link_enabled: form.value.magic_link_enabled,
    })

    applySettings(res.data)
    success.value = true
    setTimeout(() => { success.value = false }, 3000)
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

async function onLogoSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) {
    return
  }

  error.value = ''
  uploadingLogo.value = true

  try {
    const upload = await uploadCompanyLogo(file)
    logoPreview.value = upload.data.logo_url
    if (settings.value) {
      settings.value.profile.logo_url = upload.data.logo_url
    }
    success.value = true
    setTimeout(() => { success.value = false }, 3000)
  } catch (e) {
    if (e instanceof ApiError) {
      error.value = (e.data as { message?: string })?.message ?? 'Não foi possível enviar a logo.'
    } else {
      error.value = 'Não foi possível enviar a logo.'
    }
  } finally {
    uploadingLogo.value = false
    input.value = ''
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold text-white">Configurações da empresa</h1>
      <p class="mt-1 text-sm text-stone-400">
        Personalize os dados cadastrais, documentos, preferências operacionais e formas de acesso.
      </p>
      <RouterLink :to="tenantRoute('billing.plan')" class="mt-3 inline-flex rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-sm font-medium text-amber-300 hover:bg-amber-500/20">
        Ver plano e faturamento
      </RouterLink>
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

    <form v-else class="space-y-6" @submit.prevent="save">
      <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ error }}</div>
      <div v-if="success" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
        Configurações salvas com sucesso.
      </div>

      <div v-if="auth.subscription" class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-400">Assinatura atual</h2>
        <div class="grid gap-4 md:grid-cols-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-stone-500">Plano</p>
            <p class="mt-1 text-sm text-white">{{ auth.subscription.plan.name ?? '—' }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-widest text-stone-500">Status</p>
            <p class="mt-1 text-sm capitalize text-white">{{ auth.subscription.status }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-widest text-stone-500">Validade</p>
            <p class="mt-1 text-sm text-white">{{ auth.subscription.expires_at ? new Date(auth.subscription.expires_at).toLocaleDateString('pt-BR') : '—' }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-widest text-stone-500">OS no mês</p>
            <p class="mt-1 text-sm text-white">{{ auth.subscription.usage.service_orders_this_month }}</p>
          </div>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="space-y-6">
          <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Dados cadastrais</h2>
            <div class="grid gap-4 md:grid-cols-2">
              <label class="space-y-1">
                <span class="text-sm text-stone-300">Razão social</span>
                <input v-model="form.name" class="input" />
              </label>
              <label class="space-y-1">
                <span class="text-sm text-stone-300">Nome fantasia</span>
                <input v-model="form.trade_name" class="input" />
              </label>
              <label class="space-y-1 md:col-span-2">
                <span class="text-sm text-stone-300">Slug da empresa</span>
                <input v-model="form.slug" class="input" placeholder="techfix" />
                <p class="text-xs text-stone-500">
                  URL atual: {{ settings?.profile.tenant_url ?? '—' }}
                </p>
              </label>
              <label class="space-y-1">
                <span class="text-sm text-stone-300">CNPJ ou CPF</span>
                <input v-model="form.document" class="input" placeholder="00.000.000/0000-00" />
              </label>
              <label class="space-y-1">
                <span class="text-sm text-stone-300">Telefone comercial</span>
                <input v-model="form.phone" class="input" />
              </label>
              <label class="space-y-1 md:col-span-2">
                <span class="text-sm text-stone-300">E-mail comercial</span>
                <input v-model="form.email" type="email" class="input" />
              </label>
              <label class="space-y-1 md:col-span-2">
                <span class="text-sm text-stone-300">Site</span>
                <input v-model="form.website" type="url" class="input" placeholder="https://suaempresa.com.br" />
              </label>
              <label class="space-y-1 md:col-span-2">
                <span class="text-sm text-stone-300">Logradouro e número</span>
                <input v-model="form.address_line_1" class="input" />
              </label>
              <label class="space-y-1 md:col-span-2">
                <span class="text-sm text-stone-300">Complemento</span>
                <input v-model="form.address_line_2" class="input" />
              </label>
              <label class="space-y-1">
                <span class="text-sm text-stone-300">Bairro</span>
                <input v-model="form.district" class="input" />
              </label>
              <label class="space-y-1">
                <span class="text-sm text-stone-300">CEP</span>
                <input v-model="form.postal_code" class="input" />
              </label>
              <label class="space-y-1">
                <span class="text-sm text-stone-300">Cidade</span>
                <input v-model="form.city" class="input" />
              </label>
              <label class="space-y-1">
                <span class="text-sm text-stone-300">UF</span>
                <input v-model="form.state" maxlength="2" class="input uppercase" />
              </label>
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Documentos e identidade visual</h2>
            <div class="grid gap-4 md:grid-cols-2">
              <label class="space-y-1">
                <span class="text-sm text-stone-300">Nome da marca</span>
                <input v-model="form.brand_name" class="input" placeholder="Nome exibido nos documentos e portal" />
              </label>
              <label class="space-y-1">
                <span class="text-sm text-stone-300">Linha de contato</span>
                <input v-model="form.customer_contact_line" class="input" placeholder="WhatsApp (11) 99999-9999" />
              </label>
              <label class="space-y-1 md:col-span-2">
                <span class="text-sm text-stone-300">Rodapé dos documentos</span>
                <textarea v-model="form.document_footer_text" rows="3" class="input resize-none" placeholder="Garantia de 90 dias nos serviços" />
              </label>
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Preferências operacionais</h2>
            <div class="grid gap-4 md:grid-cols-2">
              <label class="space-y-1">
                <span class="text-sm text-stone-300">Prefixo da numeração</span>
                <input v-model="form.service_order_reference_prefix" class="input uppercase" maxlength="20" />
              </label>
              <label class="space-y-1">
                <span class="text-sm text-stone-300">Validade padrão do orçamento</span>
                <input v-model.number="form.default_quote_validity_days" type="number" min="1" max="30" class="input" />
              </label>
              <label class="space-y-1">
                <span class="text-sm text-stone-300">Moeda</span>
                <select v-model="form.currency" class="input">
                  <option value="BRL">BRL</option>
                  <option value="USD">USD</option>
                </select>
              </label>
            </div>

            <div class="mt-4 space-y-3">
              <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-black/20 p-4">
                <input v-model="form.intake_checklists_enabled" type="checkbox" class="mt-1 size-4 rounded accent-amber-500" />
                <div>
                  <p class="text-sm text-stone-200">Habilitar checklists de recebimento</p>
                  <p class="text-xs text-stone-500">Mantém o módulo de checklist disponível no fluxo de abertura da O.S.</p>
                </div>
              </label>

              <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-black/20 p-4">
                <input v-model="form.block_delivery_with_outstanding_balance" type="checkbox" class="mt-1 size-4 rounded accent-amber-500" />
                <div>
                  <p class="text-sm text-stone-200">Bloquear entrega com saldo pendente</p>
                  <p class="text-xs text-stone-500">Impede a baixa final quando ainda existe valor em aberto.</p>
                </div>
              </label>
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Portal do cliente</h2>
            <div class="space-y-4">
              <label class="space-y-1">
                <span class="text-sm text-stone-300">Texto de introdução</span>
                <textarea v-model="form.portal_intro_text" rows="4" class="input resize-none" />
              </label>
              <div class="grid gap-4 md:grid-cols-2">
                <label class="space-y-1">
                  <span class="text-sm text-stone-300">Mensagem após aprovação</span>
                  <input v-model="form.portal_approved_text" class="input" />
                </label>
                <label class="space-y-1">
                  <span class="text-sm text-stone-300">Mensagem após recusa</span>
                  <input v-model="form.portal_rejected_text" class="input" />
                </label>
              </div>
              <label class="space-y-1">
                <span class="text-sm text-stone-300">Rodapé do portal</span>
                <textarea v-model="form.portal_footer_text" rows="3" class="input resize-none" />
              </label>
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Logo da empresa</h2>
            <div class="space-y-4">
              <div class="flex min-h-48 items-center justify-center rounded-2xl border border-dashed border-white/10 bg-black/20 p-4">
                <img v-if="logoPreview" :src="logoPreview" alt="Preview da logo" class="max-h-40 max-w-full object-contain" />
                <p v-else class="text-sm text-stone-500">Nenhuma logo enviada.</p>
              </div>

              <label class="block">
                <span class="mb-2 block text-sm text-stone-300">Enviar nova logo</span>
                <input type="file" accept="image/png,image/jpeg,image/webp" class="input file:mr-4 file:rounded-lg file:border-0 file:bg-amber-500 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-stone-950" @change="onLogoSelected" />
              </label>

              <p class="text-xs text-stone-500">PNG, JPG ou WEBP com até 2 MB.</p>
              <p v-if="uploadingLogo" class="text-sm text-amber-300">Enviando logo…</p>
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Comunicações</h2>
            <label class="space-y-1">
              <span class="text-sm text-stone-300">Rodapé dos e-mails</span>
              <textarea v-model="form.notification_footer_text" rows="3" class="input resize-none" />
            </label>

            <div class="mt-4 space-y-4">
              <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                <label class="flex cursor-pointer items-start gap-3">
                  <input v-model="form.notify_quote_available" type="checkbox" class="mt-1 size-4 rounded accent-amber-500" />
                  <div>
                    <p class="text-sm text-stone-200">Orçamento disponível</p>
                    <p class="text-xs text-stone-500">Envia aviso quando um orçamento for gerado.</p>
                  </div>
                </label>
                <input v-model="form.notify_quote_available_text" class="input mt-4" placeholder="Mensagem adicional" />
              </div>

              <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                <label class="flex cursor-pointer items-start gap-3">
                  <input v-model="form.notify_quote_expiring" type="checkbox" class="mt-1 size-4 rounded accent-amber-500" />
                  <div>
                    <p class="text-sm text-stone-200">Orçamento expirando</p>
                    <p class="text-xs text-stone-500">Lembrete quando o prazo estiver perto do vencimento.</p>
                  </div>
                </label>
                <input v-model="form.notify_quote_expiring_text" class="input mt-4" placeholder="Mensagem adicional" />
              </div>

              <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                <label class="flex cursor-pointer items-start gap-3">
                  <input v-model="form.notify_ready_for_pickup" type="checkbox" class="mt-1 size-4 rounded accent-amber-500" />
                  <div>
                    <p class="text-sm text-stone-200">Pronto para retirada</p>
                    <p class="text-xs text-stone-500">Notifica quando o equipamento estiver pronto.</p>
                  </div>
                </label>
                <input v-model="form.notify_ready_for_pickup_text" class="input mt-4" placeholder="Mensagem adicional" />
              </div>

              <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                <label class="flex cursor-pointer items-start gap-3">
                  <input v-model="form.notify_payment_receipt" type="checkbox" class="mt-1 size-4 rounded accent-amber-500" />
                  <div>
                    <p class="text-sm text-stone-200">Comprovante de pagamento</p>
                    <p class="text-xs text-stone-500">Envia recibo após registro de pagamento.</p>
                  </div>
                </label>
                <input v-model="form.notify_payment_receipt_text" class="input mt-4" placeholder="Mensagem adicional" />
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Acesso</h2>
            <div class="space-y-3">
              <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-black/20 p-4">
                <input v-model="form.passwordless_code_enabled" type="checkbox" class="mt-1 size-4 rounded accent-amber-500" />
                <div>
                  <p class="text-sm text-stone-200">Habilitar login por código</p>
                  <p class="text-xs text-stone-500">Permite que membros recebam código por e-mail.</p>
                </div>
              </label>

              <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-black/20 p-4">
                <input v-model="form.magic_link_enabled" type="checkbox" class="mt-1 size-4 rounded accent-amber-500" />
                <div>
                  <p class="text-sm text-stone-200">Habilitar magic link</p>
                  <p class="text-xs text-stone-500">Permite acesso sem senha via link temporário.</p>
                </div>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <button
          type="submit"
          :disabled="saving"
          class="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
        >
          {{ saving ? 'Salvando…' : 'Salvar configurações' }}
        </button>
      </div>
    </form>
  </div>
</template>
