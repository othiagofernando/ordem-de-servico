<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { API_BASE } from '../../api/client'

const route = useRoute()
const token = route.params.token as string

interface PortalData {
  quote_status: string
  service_order: { title: string; order_number: string | null; status: string }
  company: { brand_name: string | null; portal_intro_text: string | null }
  equipment: { description: string | null; brand: string | null; model: string | null } | null
  customer: { name: string } | null
  line_items: Array<{ description: string; quantity: number; unit_price_cents: number }>
  total_cents: number
  currency: string
  valid_until: string
  diagnosis_customer_summary: string | null
  notes: string | null
}

const data = ref<PortalData | null>(null)
const loading = ref(true)
const error = ref('')
const deciding = ref(false)
const decided = ref<'approved' | 'rejected' | null>(null)
const conflictMessage = ref('')

async function load() {
  try {
    const res = await fetch(`${API_BASE}/portal/quotes/${token}`, {
      headers: { Accept: 'application/json' },
    })
    if (!res.ok) {
      error.value = res.status === 404 ? 'Orçamento não encontrado ou link inválido.' : 'Erro ao carregar orçamento.'
      return
    }
    const body = await res.json()
    data.value = body.data

    if (['approved', 'rejected', 'expired'].includes(data.value!.quote_status)) {
      decided.value = data.value!.quote_status as 'approved' | 'rejected'
    }
  } catch {
    error.value = 'Não foi possível conectar ao servidor.'
  } finally {
    loading.value = false
  }
}

async function decide(decision: 'approve' | 'reject') {
  deciding.value = true
  conflictMessage.value = ''
  try {
    const res = await fetch(`${API_BASE}/portal/quotes/${token}/decision`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ decision }),
    })
    const body = await res.json()
    if (res.status === 409) {
      conflictMessage.value = 'Este orçamento já teve uma decisão registrada.'
      return
    }
    if (!res.ok) {
      conflictMessage.value = body.message ?? 'Erro ao registrar decisão.'
      return
    }
    decided.value = decision === 'approve' ? 'approved' : 'rejected'
  } finally {
    deciding.value = false
  }
}

function fmtMoney(cents: number) {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function fmtDate(dt: string) {
  return new Date(dt).toLocaleDateString('pt-BR')
}

onMounted(load)
</script>

<template>
  <div class="min-h-screen px-4 py-10">
    <div class="mx-auto max-w-xl">
      <!-- Header -->
      <div class="mb-8 text-center">
        <p class="text-2xl font-bold text-amber-400">{{ data?.company?.brand_name ?? 'Portal do Cliente' }}</p>
        <p class="mt-1 text-sm text-stone-400">Orçamento de Serviço</p>
      </div>

      <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

      <div v-else-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 p-8 text-center text-red-300">
        {{ error }}
      </div>

      <template v-else-if="data">
        <!-- Intro -->
        <div v-if="data.company.portal_intro_text" class="mb-6 rounded-2xl border border-white/10 bg-white/5 p-5 text-sm text-stone-300">
          {{ data.company.portal_intro_text }}
        </div>

        <!-- OS info -->
        <div class="mb-4 rounded-2xl border border-white/10 bg-white/5 p-6">
          <p class="text-xs text-stone-500">Ordem de Serviço</p>
          <p class="mt-0.5 text-lg font-semibold text-white">{{ data.service_order.order_number ?? data.service_order.title }}</p>

          <div v-if="data.customer" class="mt-3">
            <p class="text-xs text-stone-500">Cliente</p>
            <p class="text-sm text-stone-200">{{ data.customer.name }}</p>
          </div>

          <div v-if="data.equipment" class="mt-3">
            <p class="text-xs text-stone-500">Equipamento</p>
            <p class="text-sm text-stone-200">{{ [data.equipment.brand, data.equipment.model].filter(Boolean).join(' ') || data.equipment.description }}</p>
          </div>

          <div v-if="data.diagnosis_customer_summary" class="mt-4 border-t border-white/5 pt-4">
            <p class="text-xs text-stone-500">Diagnóstico</p>
            <p class="mt-1 text-sm text-stone-300">{{ data.diagnosis_customer_summary }}</p>
          </div>
        </div>

        <!-- Itens do orçamento -->
        <div class="mb-4 rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold text-stone-300">Itens do Orçamento</h2>
          <div class="space-y-2">
            <div
              v-for="(item, i) in data.line_items"
              :key="i"
              class="flex justify-between text-sm"
            >
              <span class="text-stone-400">{{ item.quantity }}x {{ item.description }}</span>
              <span class="text-stone-200">{{ fmtMoney(item.unit_price_cents * item.quantity) }}</span>
            </div>
          </div>
          <div class="mt-4 flex justify-between border-t border-white/10 pt-3 text-base font-semibold">
            <span class="text-stone-300">Total</span>
            <span class="text-amber-300">{{ fmtMoney(data.total_cents) }}</span>
          </div>
          <p class="mt-2 text-xs text-stone-500">Válido até {{ fmtDate(data.valid_until) }}</p>
          <p v-if="data.notes" class="mt-2 text-xs text-stone-400">{{ data.notes }}</p>
        </div>

        <!-- Decisão -->
        <div v-if="conflictMessage" class="mb-4 rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300">
          {{ conflictMessage }}
        </div>

        <div v-if="decided === 'approved'" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-6 text-center">
          <p class="text-2xl">✓</p>
          <p class="mt-2 font-semibold text-emerald-300">Orçamento aprovado!</p>
          <p class="mt-1 text-sm text-stone-400">Nossa equipe foi notificada e dará início ao serviço em breve.</p>
        </div>

        <div v-else-if="decided === 'rejected'" class="rounded-2xl border border-red-500/20 bg-red-500/10 p-6 text-center">
          <p class="text-2xl">✕</p>
          <p class="mt-2 font-semibold text-red-300">Orçamento recusado.</p>
          <p class="mt-1 text-sm text-stone-400">Entraremos em contato para mais informações.</p>
        </div>

        <div v-else-if="data.quote_status === 'expired'" class="rounded-2xl border border-stone-500/20 bg-stone-500/10 p-6 text-center">
          <p class="font-semibold text-stone-400">Este orçamento expirou.</p>
          <p class="mt-1 text-sm text-stone-500">Entre em contato conosco para solicitar um novo orçamento.</p>
        </div>

        <div v-else class="flex gap-3">
          <button
            :disabled="deciding"
            class="flex-1 rounded-2xl bg-emerald-600 py-3.5 text-center font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-50"
            @click="decide('approve')"
          >
            {{ deciding ? '…' : 'Aprovar Orçamento' }}
          </button>
          <button
            :disabled="deciding"
            class="flex-1 rounded-2xl border border-red-500/30 bg-red-500/10 py-3.5 text-center font-semibold text-red-300 transition hover:bg-red-500/20 disabled:opacity-50"
            @click="decide('reject')"
          >
            {{ deciding ? '…' : 'Recusar' }}
          </button>
        </div>
      </template>
    </div>
  </div>
</template>
