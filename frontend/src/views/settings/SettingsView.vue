<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getSettings, updateSettings, type CompanySettings } from '../../api/settings'
import { ApiError } from '../../api/client'

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref(false)
const settings = ref<CompanySettings | null>(null)

const form = ref({
  brand_name: '',
  customer_contact_line: '',
  service_order_reference_prefix: 'OS',
  portal_intro_text: '',
  portal_footer_text: '',
  portal_approved_text: '',
  portal_rejected_text: '',
  default_quote_validity_days: 7,
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

onMounted(async () => {
  try {
    const res = await getSettings()
    settings.value = res.data
    form.value = {
      brand_name: res.data.branding.brand_name ?? '',
      customer_contact_line: res.data.branding.customer_contact_line ?? '',
      service_order_reference_prefix: res.data.service_orders.reference_prefix,
      portal_intro_text: res.data.portal.intro_text ?? '',
      portal_footer_text: res.data.portal.footer_text ?? '',
      portal_approved_text: res.data.portal.approved_text ?? '',
      portal_rejected_text: res.data.portal.rejected_text ?? '',
      default_quote_validity_days: res.data.quotes.default_validity_days,
      block_delivery_with_outstanding_balance: res.data.finance.block_delivery_with_outstanding_balance,
      notify_quote_available: res.data.notifications.quote_available,
      notify_quote_available_text: res.data.notifications.quote_available_text ?? '',
      notify_quote_expiring: res.data.notifications.quote_expiring,
      notify_quote_expiring_text: res.data.notifications.quote_expiring_text ?? '',
      notify_ready_for_pickup: res.data.notifications.ready_for_pickup,
      notify_ready_for_pickup_text: res.data.notifications.ready_for_pickup_text ?? '',
      notify_payment_receipt: res.data.notifications.payment_receipt,
      notify_payment_receipt_text: res.data.notifications.payment_receipt_text ?? '',
      notification_footer_text: res.data.communications.notification_footer_text ?? '',
      passwordless_code_enabled: res.data.auth.passwordless_code_enabled,
      magic_link_enabled: res.data.auth.magic_link_enabled,
    }
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
      brand_name: form.value.brand_name || null,
      customer_contact_line: form.value.customer_contact_line || null,
      service_order_reference_prefix: form.value.service_order_reference_prefix || null,
      portal_intro_text: form.value.portal_intro_text || null,
      portal_footer_text: form.value.portal_footer_text || null,
      portal_approved_text: form.value.portal_approved_text || null,
      portal_rejected_text: form.value.portal_rejected_text || null,
      default_quote_validity_days: form.value.default_quote_validity_days,
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
    settings.value = res.data
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
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold text-white">Configurações da Empresa</h1>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

    <form v-else class="space-y-6" @submit.prevent="save">
      <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ error }}</div>
      <div v-if="success" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">Configurações salvas com sucesso.</div>

      <!-- Marca -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Marca</h2>
        <div>
          <label class="mb-1.5 block text-sm text-stone-300">Nome da Marca</label>
          <input v-model="form.brand_name" class="input" placeholder="Nome exibido no portal do cliente" />
          <p class="mt-1 text-xs text-stone-500">Se vazio, usa o nome cadastrado da empresa.</p>
        </div>
        <div class="mt-4">
          <label class="mb-1.5 block text-sm text-stone-300">Linha de Contato ao Cliente</label>
          <input v-model="form.customer_contact_line" class="input" placeholder="WhatsApp (11) 99999-9999" />
          <p class="mt-1 text-xs text-stone-500">Exibida no portal do cliente e nas comunicações por e-mail.</p>
        </div>
      </div>

      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Ordem de Serviço</h2>
        <div>
          <label class="mb-1.5 block text-sm text-stone-300">Prefixo da Numeração</label>
          <input
            v-model="form.service_order_reference_prefix"
            class="input w-48 uppercase"
            maxlength="20"
            placeholder="OS"
          />
          <p class="mt-1 text-xs text-stone-500">Aplicado às próximas O.S. abertas e aos retornos em garantia. Ex.: OS-000123.</p>
        </div>
      </div>

      <!-- Portal do Cliente -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Portal do Cliente</h2>
        <div>
          <label class="mb-1.5 block text-sm text-stone-300">Texto de Introdução</label>
          <textarea v-model="form.portal_intro_text" rows="4" class="input resize-none" placeholder="Texto exibido no topo do portal de orçamentos…" />
        </div>
        <div class="mt-4">
          <label class="mb-1.5 block text-sm text-stone-300">Texto após Aprovação</label>
          <input v-model="form.portal_approved_text" class="input" placeholder="Mensagem exibida após aprovar o orçamento" />
        </div>
        <div class="mt-4">
          <label class="mb-1.5 block text-sm text-stone-300">Texto após Recusa</label>
          <input v-model="form.portal_rejected_text" class="input" placeholder="Mensagem exibida após recusar o orçamento" />
        </div>
        <div class="mt-4">
          <label class="mb-1.5 block text-sm text-stone-300">Rodapé do Portal</label>
          <textarea v-model="form.portal_footer_text" rows="3" class="input resize-none" placeholder="Texto complementar exibido no rodapé do portal…" />
        </div>
      </div>

      <!-- Orçamentos -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Orçamentos</h2>
        <div>
          <label class="mb-1.5 block text-sm text-stone-300">Validade Padrão (dias)</label>
          <input v-model.number="form.default_quote_validity_days" type="number" min="1" max="30" class="input w-40" />
        </div>
      </div>

      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Financeiro Operacional</h2>
        <div class="space-y-4">
          <label class="flex cursor-pointer items-center gap-3">
            <input v-model="form.block_delivery_with_outstanding_balance" type="checkbox" class="size-4 rounded accent-amber-500" />
            <div>
              <p class="text-sm text-stone-200">Bloquear entrega com saldo pendente</p>
              <p class="text-xs text-stone-500">Quando ativo, a entrega exige quitação total ou override privilegiado.</p>
            </div>
          </label>
        </div>
      </div>

      <!-- Notificações -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Notificações por E-mail</h2>
        <div class="mb-5">
          <label class="mb-1.5 block text-sm text-stone-300">Rodapé das Comunicações</label>
          <textarea
            v-model="form.notification_footer_text"
            rows="3"
            class="input resize-none"
            placeholder="Mensagem complementar exibida ao final dos e-mails ao cliente…"
          />
        </div>
        <div class="space-y-4">
          <div class="rounded-xl border border-white/10 bg-black/20 p-4">
            <label class="flex cursor-pointer items-center gap-3">
              <input v-model="form.notify_quote_available" type="checkbox" class="size-4 rounded accent-amber-500" />
              <div>
                <p class="text-sm text-stone-200">Orçamento disponível</p>
                <p class="text-xs text-stone-500">Notifica o cliente quando um novo orçamento for gerado.</p>
              </div>
            </label>
            <div class="mt-4">
              <label class="mb-1.5 block text-sm text-stone-300">Mensagem do evento</label>
              <input v-model="form.notify_quote_available_text" class="input" placeholder="Mensagem adicional para este e-mail…" />
            </div>
          </div>
          <div class="rounded-xl border border-white/10 bg-black/20 p-4">
            <label class="flex cursor-pointer items-center gap-3">
              <input v-model="form.notify_quote_expiring" type="checkbox" class="size-4 rounded accent-amber-500" />
              <div>
                <p class="text-sm text-stone-200">Orçamento expirando</p>
                <p class="text-xs text-stone-500">Lembrete quando o prazo do orçamento estiver próximo do vencimento.</p>
              </div>
            </label>
            <div class="mt-4">
              <label class="mb-1.5 block text-sm text-stone-300">Mensagem do evento</label>
              <input v-model="form.notify_quote_expiring_text" class="input" placeholder="Mensagem adicional para este e-mail…" />
            </div>
          </div>
          <div class="rounded-xl border border-white/10 bg-black/20 p-4">
            <label class="flex cursor-pointer items-center gap-3">
              <input v-model="form.notify_ready_for_pickup" type="checkbox" class="size-4 rounded accent-amber-500" />
              <div>
                <p class="text-sm text-stone-200">Pronto para retirada</p>
                <p class="text-xs text-stone-500">Notifica o cliente quando o equipamento estiver pronto.</p>
              </div>
            </label>
            <div class="mt-4">
              <label class="mb-1.5 block text-sm text-stone-300">Mensagem do evento</label>
              <input v-model="form.notify_ready_for_pickup_text" class="input" placeholder="Mensagem adicional para este e-mail…" />
            </div>
          </div>
          <div class="rounded-xl border border-white/10 bg-black/20 p-4">
            <label class="flex cursor-pointer items-center gap-3">
              <input v-model="form.notify_payment_receipt" type="checkbox" class="size-4 rounded accent-amber-500" />
              <div>
                <p class="text-sm text-stone-200">Comprovante de pagamento</p>
                <p class="text-xs text-stone-500">Envia recibo ao cliente após registrar um pagamento.</p>
              </div>
            </label>
            <div class="mt-4">
              <label class="mb-1.5 block text-sm text-stone-300">Mensagem do evento</label>
              <input v-model="form.notify_payment_receipt_text" class="input" placeholder="Mensagem adicional para este e-mail…" />
            </div>
          </div>
        </div>
      </div>

      <!-- Autenticação -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-5 text-sm font-semibold uppercase tracking-widest text-stone-400">Autenticação</h2>
        <div class="space-y-4">
          <label class="flex items-center gap-3 cursor-pointer">
            <input v-model="form.passwordless_code_enabled" type="checkbox" class="size-4 accent-amber-500 rounded" />
            <div>
              <p class="text-sm text-stone-200">Login por código</p>
              <p class="text-xs text-stone-500">Permite que colaboradores acessem via código de 6 dígitos enviado por e-mail.</p>
            </div>
          </label>
          <label class="flex items-center gap-3 cursor-pointer">
            <input v-model="form.magic_link_enabled" type="checkbox" class="size-4 accent-amber-500 rounded" />
            <div>
              <p class="text-sm text-stone-200">Login por magic link</p>
              <p class="text-xs text-stone-500">Permite que colaboradores acessem via link enviado por e-mail, sem senha.</p>
            </div>
          </label>
        </div>
      </div>

      <div class="flex gap-3">
        <button
          type="submit"
          :disabled="saving"
          class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
        >
          {{ saving ? 'Salvando…' : 'Salvar Configurações' }}
        </button>
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
