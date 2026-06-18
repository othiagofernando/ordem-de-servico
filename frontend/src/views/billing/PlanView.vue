<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ApiError } from '../../api/client'
import { cancelBillingSubscription, checkoutBilling, getSubscriptionBilling, type BillingInvoice, type BillingPlan } from '../../api/billing'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const loading = ref(true)
const error = ref('')
const plans = ref<BillingPlan[]>([])
const invoices = ref<BillingInvoice[]>([])
const checkoutLoadingId = ref<number | null>(null)
const cancelling = ref(false)
const confirmation = ref('')
const pendingPayment = ref<{ invoice_url: string | null; pdf_url: string | null; payment_hint: string | null } | null>(null)

const current = computed(() => auth.subscription)

function formatMoney(cents: number) {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await getSubscriptionBilling()
    auth.subscription = res.data
    plans.value = res.plans
    invoices.value = res.invoices
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Não foi possível carregar o plano.'
  } finally {
    loading.value = false
  }
}

async function startCheckout(planId: number) {
  checkoutLoadingId.value = planId
  error.value = ''
  try {
    const res = await checkoutBilling(planId)
    pendingPayment.value = {
      invoice_url: res.data.invoice_url,
      pdf_url: res.data.pdf_url,
      payment_hint: res.data.payment_hint,
    }
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Não foi possível iniciar o checkout.'
  } finally {
    checkoutLoadingId.value = null
  }
}

async function cancelSubscription() {
  if (!auth.company?.name || confirmation.value !== auth.company.name) return
  cancelling.value = true
  error.value = ''
  try {
    await cancelBillingSubscription(confirmation.value)
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Não foi possível cancelar a assinatura.'
  } finally {
    cancelling.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold text-white">Plano e assinatura</h1>
      <p class="mt-1 text-sm text-stone-400">Gerencie upgrade, renovação e faturas da empresa.</p>
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>
    <div v-else class="space-y-6">
      <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ error }}</div>

      <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5 md:col-span-2">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Plano atual</p>
          <p class="mt-2 text-2xl font-semibold text-white">{{ current?.plan.name ?? 'Sem plano' }}</p>
          <p class="mt-2 text-sm text-stone-400">
            Validade: {{ current?.expires_at ? new Date(current.expires_at).toLocaleDateString('pt-BR') : '—' }}
          </p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Status</p>
          <p class="mt-2 text-2xl font-semibold text-white capitalize">{{ current?.status ?? '—' }}</p>
          <p class="mt-2 text-sm text-stone-400">Assinatura e faturamento manual/automático.</p>
        </div>
      </div>

      <div v-if="pendingPayment" class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-6">
        <h2 class="text-sm font-semibold uppercase tracking-widest text-emerald-300">Cobranca gerada</h2>
        <p class="mt-2 text-sm text-stone-300">{{ pendingPayment.payment_hint || 'Use os links abaixo para concluir o pagamento.' }}</p>
        <div class="mt-4 flex flex-wrap gap-3">
          <a
            v-if="pendingPayment.invoice_url"
            :href="pendingPayment.invoice_url"
            target="_blank"
            class="rounded-xl bg-emerald-400 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-emerald-300"
          >
            Abrir cobranca
          </a>
          <a
            v-if="pendingPayment.pdf_url"
            :href="pendingPayment.pdf_url"
            target="_blank"
            class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10"
          >
            Baixar PDF
          </a>
        </div>
      </div>

      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Planos disponíveis</h2>
        <div class="mt-4 grid gap-4 xl:grid-cols-3">
          <div v-for="plan in plans" :key="plan.id" class="rounded-2xl border border-white/10 bg-black/20 p-5">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-lg font-semibold text-white">{{ plan.name }}</p>
                <p class="text-sm text-stone-500">{{ plan.slug }}</p>
              </div>
              <p class="text-lg font-semibold text-amber-300">
                {{ plan.reference_price_cents ? formatMoney(plan.reference_price_cents) : 'Sob consulta' }}
              </p>
            </div>
            <div class="mt-4 space-y-1 text-sm text-stone-300">
              <p>Usuários: {{ plan.limits.max_users ?? 'ilimitado' }}</p>
              <p>OS/mês: {{ plan.limits.max_service_orders_per_month ?? 'ilimitado' }}</p>
              <p>Anexos: {{ plan.limits.max_attachments_storage_mb ?? 'ilimitado' }} MB</p>
            </div>
            <button
              class="mt-5 w-full rounded-xl bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-300 disabled:opacity-50"
              :disabled="checkoutLoadingId === plan.id"
              @click="startCheckout(plan.id)"
            >
              {{ checkoutLoadingId === plan.id ? 'Abrindo checkout…' : 'Contratar / Alterar plano' }}
            </button>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Faturas</h2>
        <div class="mt-4 overflow-hidden rounded-2xl border border-white/10">
          <table class="min-w-full divide-y divide-white/10 text-sm">
            <thead class="bg-black/20 text-left text-xs uppercase tracking-widest text-stone-500">
              <tr>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Valor</th>
                <th class="px-4 py-3">Vencimento</th>
                <th class="px-4 py-3">PDF</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="invoice in invoices" :key="invoice.id">
                <td class="px-4 py-3 text-stone-300 capitalize">{{ invoice.status }}</td>
                <td class="px-4 py-3 text-stone-300">{{ formatMoney(invoice.amount_cents) }}</td>
                <td class="px-4 py-3 text-stone-400">{{ invoice.due_at ? new Date(invoice.due_at).toLocaleDateString('pt-BR') : '—' }}</td>
                <td class="px-4 py-3">
                  <a
                    v-if="invoice.pdf_url"
                    :href="invoice.pdf_url"
                    target="_blank"
                    class="text-amber-300 hover:text-amber-200"
                  >
                    Baixar
                  </a>
                  <span v-else class="text-stone-600">—</span>
                </td>
              </tr>
              <tr v-if="invoices.length === 0">
                <td class="px-4 py-6 text-center text-stone-500" colspan="4">Nenhuma fatura encontrada.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="rounded-2xl border border-rose-500/20 bg-rose-500/5 p-6">
        <h2 class="text-sm font-semibold uppercase tracking-widest text-rose-300">Cancelar assinatura</h2>
        <p class="mt-2 text-sm text-stone-300">
          A confirmação deve ser exatamente o nome da empresa. Isso corta o acesso ao término do ciclo atual.
        </p>
        <div class="mt-4 flex flex-col gap-3 md:flex-row">
          <input
            v-model="confirmation"
            type="text"
            class="flex-1 rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white outline-none"
            :placeholder="auth.company?.name ?? 'Nome da empresa'"
          />
          <button
            class="rounded-xl bg-rose-400 px-4 py-3 text-sm font-semibold text-stone-950 hover:bg-rose-300 disabled:opacity-50"
            :disabled="cancelling"
            @click="cancelSubscription"
          >
            {{ cancelling ? 'Cancelando…' : 'Cancelar assinatura' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
