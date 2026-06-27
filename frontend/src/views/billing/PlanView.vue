<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { ApiError } from '../../api/client'
import { cancelBillingSubscription, checkoutBilling, getSubscriptionBilling, type BillingInvoice, type BillingPlan } from '../../api/billing'
import { useAuthStore } from '../../stores/auth'
import { useTenantRouting } from '../../composables/useTenantRouting'

const { tenantRoute } = useTenantRouting()

const auth = useAuthStore()
const loading = ref(true)
const error = ref('')
const missingDocument = ref(false)
const plans = ref<BillingPlan[]>([])
const invoices = ref<BillingInvoice[]>([])
const checkoutLoadingId = ref<number | null>(null)
const cancelling = ref(false)
const confirmation = ref('')
const pendingPayment = ref<{ invoice_url: string | null; pdf_url: string | null; payment_hint: string | null } | null>(null)
const justActivated = ref(false)

let pollTimer: ReturnType<typeof setInterval> | null = null

const current = computed(() => auth.subscription)

function formatMoney(cents: number) {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function startPolling() {
  if (pollTimer) return
  pollTimer = setInterval(async () => {
    const prevStatus = auth.subscription?.status
    await auth.refreshSubscription()
    if (prevStatus !== 'active' && auth.subscription?.status === 'active') {
      stopPolling()
      pendingPayment.value = null
      justActivated.value = true
      await load()
    }
  }, 5000)
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
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
  missingDocument.value = false
  try {
    const res = await checkoutBilling(planId)
    pendingPayment.value = {
      invoice_url: res.data.invoice_url,
      pdf_url: res.data.pdf_url,
      payment_hint: res.data.payment_hint,
    }
    await load()
    startPolling()
  } catch (e) {
    if (e instanceof ApiError && e.message === 'missing_document') {
      missingDocument.value = true
    } else {
      error.value = e instanceof ApiError ? e.message : 'Não foi possível iniciar o checkout.'
    }
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

// Para o polling quando a página é desmontada
onMounted(load)
onUnmounted(stopPolling)

// Também detecta ativação via visibilitychange (AppLayout já chama refreshSubscription)
watch(
  () => auth.subscription?.status,
  (newStatus, oldStatus) => {
    if (pendingPayment.value && oldStatus !== 'active' && newStatus === 'active') {
      stopPolling()
      pendingPayment.value = null
      justActivated.value = true
      load()
    }
  },
)
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

      <div v-if="justActivated" class="flex items-center gap-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3">
        <svg class="size-5 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
        </svg>
        <p class="text-sm text-emerald-300">
          <strong class="font-semibold">Assinatura ativada com sucesso!</strong>
          Seu acesso completo foi restaurado.
        </p>
      </div>

      <div
        v-if="missingDocument"
        class="flex items-center justify-between gap-4 rounded-2xl border border-amber-500/20 bg-amber-500/10 px-4 py-3"
      >
        <p class="text-sm text-amber-200">
          <strong class="font-semibold">CPF/CNPJ não preenchido.</strong>
          Para contratar um plano, preencha o documento da empresa nas Configurações.
        </p>
        <RouterLink
          :to="tenantRoute('settings')"
          class="shrink-0 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-300 transition hover:bg-amber-500/20"
        >
          Ir para Configurações
        </RouterLink>
      </div>

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
            class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-stone-300 hover:bg-white/10"
          >
            Baixar PDF
          </a>
        </div>
      </div>

      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <div class="mb-6 flex items-center justify-between gap-4">
          <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Planos disponíveis</h2>
          <p class="text-xs text-stone-500">Cobrado mensalmente · Cancele quando quiser</p>
        </div>
        <div class="grid gap-4 xl:grid-cols-3">
          <div
            v-for="plan in plans"
            :key="plan.id"
            class="relative flex flex-col rounded-2xl border p-5 transition-all"
            :class="plan.slug === 'profissional'
              ? 'border-amber-500/40 bg-amber-500/5'
              : 'border-white/10 bg-black/20'"
          >
            <!-- Badge: Mais popular -->
            <div v-if="plan.slug === 'profissional'" class="absolute -top-3.5 left-1/2 -translate-x-1/2">
              <span class="rounded-full bg-amber-400 px-3 py-1 text-xs font-semibold text-stone-950 shadow">
                Mais popular
              </span>
            </div>

            <!-- Badge: Plano atual -->
            <div v-if="current?.plan.id === plan.id" class="mb-4 inline-flex items-center gap-1.5 self-start rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-300">
              <span class="size-1.5 rounded-full bg-emerald-400"></span>
              Plano atual
            </div>

            <!-- Nome -->
            <p class="text-base font-semibold text-white">{{ plan.name }}</p>

            <!-- Preço -->
            <div class="mt-3 mb-5">
              <template v-if="plan.reference_price_cents">
                <span class="text-3xl font-bold tracking-tight text-white">{{ formatMoney(plan.reference_price_cents) }}</span>
                <span class="ml-1 text-sm text-stone-500">/mês</span>
              </template>
              <span v-else class="text-2xl font-bold text-amber-400">Sob consulta</span>
            </div>

            <!-- Divisor -->
            <div class="mb-4 h-px" :class="plan.slug === 'profissional' ? 'bg-amber-500/20' : 'bg-white/5'"></div>

            <!-- Features -->
            <ul class="mb-6 grow space-y-2.5">
              <li class="flex items-center gap-2.5 text-sm text-stone-300">
                <svg class="size-4 shrink-0 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                <span>
                  <strong class="text-white">{{ plan.limits.max_users ?? 'Ilimitado' }}</strong>
                  usuário{{ plan.limits.max_users === 1 ? '' : 's' }}
                </span>
              </li>
              <li class="flex items-center gap-2.5 text-sm text-stone-300">
                <svg class="size-4 shrink-0 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                <span>
                  <strong class="text-white">{{ plan.limits.max_service_orders_per_month ?? 'Ilimitado' }}</strong>
                  OS/mês
                </span>
              </li>
              <li class="flex items-center gap-2.5 text-sm text-stone-300">
                <svg class="size-4 shrink-0 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                <span>
                  <strong class="text-white">{{ plan.limits.max_attachments_storage_mb ?? 'Ilimitado' }}</strong>
                  MB de armazenamento
                </span>
              </li>
            </ul>

            <!-- CTA -->
            <!-- Plano atual + ativo + validade > 30 dias: bloqueado -->
            <button
              v-if="current?.plan.id === plan.id && current?.status === 'active' && (current?.days_until_expiration ?? 999) > 30"
              class="w-full cursor-not-allowed rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-semibold text-stone-500"
              disabled
            >
              Plano ativo
            </button>
            <!-- Plano atual expirando / suspenso, ou outro plano: ação disponível -->
            <button
              v-else
              class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:opacity-50"
              :class="plan.slug === 'profissional'
                ? 'bg-amber-400 text-stone-950 hover:bg-amber-300'
                : 'border border-white/10 bg-white/5 text-stone-300 hover:bg-white/10'"
              :disabled="checkoutLoadingId === plan.id"
              @click="startCheckout(plan.id)"
            >
              <template v-if="checkoutLoadingId === plan.id">Abrindo checkout…</template>
              <template v-else-if="current?.plan.id === plan.id">Renovar plano</template>
              <template v-else-if="current?.status === 'active'">Fazer upgrade</template>
              <template v-else>Contratar plano</template>
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
