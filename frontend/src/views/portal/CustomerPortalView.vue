<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { API_BASE } from '../../api/client'

const route = useRoute()
const token = route.params.token as string

interface PortalData {
  company: { brand_name: string | null; customer_contact_line: string | null }
  portal: {
    intro_text: string | null
    footer_text: string | null
    approved_text: string
    rejected_text: string
  }
  service_order: {
    id: number
    reference: string
    order_number: string | null
    title: string | null
    status: string
    reported_problem: string | null
    diagnosis_customer_summary: string | null
    customer: { id: number; name: string } | null
    equipment: { description: string | null; category: string | null; brand: string | null; model: string | null } | null
    timeline: Array<{ id: number; type: string; title: string; occurred_at: string | null }>
  }
  quote: {
    id: number
    status: string
    revision_number: number
    line_items: Array<{ description: string; quantity: number; unit_price_cents: number }>
    total_cents: number
    currency: string
    valid_until: string | null
    notes: string | null
    decisioned_at: string | null
  }
}

const statusLabels: Record<string, string> = {
  draft: 'Rascunho',
  open: 'Aberta',
  in_progress: 'Em Execução',
  completed: 'Concluída',
  ready_for_pickup: 'Pronta para Retirada',
  delivered: 'Entregue',
  cancelled: 'Cancelada',
  no_repair: 'Sem Conserto',
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
    const portalData = body.data as PortalData
    data.value = portalData

    if (['approved', 'rejected'].includes(portalData.quote.status)) {
      decided.value = portalData.quote.status as 'approved' | 'rejected'
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
      conflictMessage.value = body.message ?? 'Este orçamento já teve uma decisão registrada.'
      return
    }

    if (!res.ok) {
      conflictMessage.value = body.message ?? 'Erro ao registrar decisão.'
      return
    }

    data.value = body.data
    decided.value = body.data.quote.status
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

function orderStatusLabel(status: string) {
  return statusLabels[status] ?? status
}

onMounted(load)
</script>

<template>
  <div class="min-h-screen px-4 py-10">
    <div class="mx-auto max-w-xl">
      <div class="mb-8 text-center">
        <p class="text-2xl font-bold text-amber-400">{{ data?.company?.brand_name ?? 'Portal do Cliente' }}</p>
        <p class="mt-1 text-sm text-stone-400">Acompanhamento da Ordem de Serviço</p>
      </div>

      <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

      <div v-else-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 p-8 text-center text-red-300">
        {{ error }}
      </div>

      <template v-else-if="data">
        <div v-if="data.portal.intro_text" class="mb-6 rounded-2xl border border-white/10 bg-white/5 p-5 text-sm text-stone-300">
          {{ data.portal.intro_text }}
        </div>

        <div class="mb-4 rounded-2xl border border-white/10 bg-white/5 p-6">
          <p class="text-xs text-stone-500">Ordem de Serviço</p>
          <div class="mt-1 flex flex-wrap items-center gap-3">
            <p class="text-lg font-semibold text-white">{{ data.service_order.order_number ?? data.service_order.reference }}</p>
            <span class="rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-semibold text-amber-300">
              {{ orderStatusLabel(data.service_order.status) }}
            </span>
          </div>
          <p v-if="data.service_order.title" class="mt-2 text-sm text-stone-400">{{ data.service_order.title }}</p>

          <div v-if="data.service_order.customer" class="mt-3">
            <p class="text-xs text-stone-500">Cliente</p>
            <p class="text-sm text-stone-200">{{ data.service_order.customer.name }}</p>
          </div>

          <div v-if="data.service_order.equipment" class="mt-3">
            <p class="text-xs text-stone-500">Equipamento</p>
            <p class="text-sm text-stone-200">
              {{ [data.service_order.equipment.brand, data.service_order.equipment.model].filter(Boolean).join(' ') || data.service_order.equipment.description }}
            </p>
          </div>

          <div v-if="data.service_order.reported_problem" class="mt-4 border-t border-white/5 pt-4">
            <p class="text-xs text-stone-500">Problema Relatado</p>
            <p class="mt-1 text-sm text-stone-300">{{ data.service_order.reported_problem }}</p>
          </div>

          <div v-if="data.service_order.diagnosis_customer_summary" class="mt-4 border-t border-white/5 pt-4">
            <p class="text-xs text-stone-500">Diagnóstico</p>
            <p class="mt-1 text-sm text-stone-300">{{ data.service_order.diagnosis_customer_summary }}</p>
          </div>
        </div>

        <div class="mb-4 rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold text-stone-300">Acompanhamento</h2>
          <div class="space-y-4">
            <div v-for="event in data.service_order.timeline" :key="event.id" class="border-l border-amber-500/30 pl-4">
              <p class="text-sm font-medium text-stone-100">{{ event.title }}</p>
              <p v-if="event.occurred_at" class="mt-1 text-xs text-stone-500">{{ fmtDate(event.occurred_at) }}</p>
            </div>
          </div>
        </div>

        <div class="mb-4 rounded-2xl border border-white/10 bg-white/5 p-6">
          <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-stone-300">Orçamento Atual</h2>
            <span class="rounded-full bg-white/5 px-2.5 py-1 text-xs text-stone-400">
              Revisão {{ data.quote.revision_number }}
            </span>
          </div>
          <div class="space-y-2">
            <div
              v-for="(item, i) in data.quote.line_items"
              :key="i"
              class="flex justify-between text-sm"
            >
              <span class="text-stone-400">{{ item.quantity }}x {{ item.description }}</span>
              <span class="text-stone-200">{{ fmtMoney(item.unit_price_cents * item.quantity) }}</span>
            </div>
          </div>
          <div class="mt-4 flex justify-between border-t border-white/10 pt-3 text-base font-semibold">
            <span class="text-stone-300">Total</span>
            <span class="text-amber-300">{{ fmtMoney(data.quote.total_cents) }}</span>
          </div>
          <p v-if="data.quote.valid_until" class="mt-2 text-xs text-stone-500">Válido até {{ fmtDate(data.quote.valid_until) }}</p>
          <p v-if="data.quote.notes" class="mt-2 text-xs text-stone-400">{{ data.quote.notes }}</p>
        </div>

        <div v-if="conflictMessage" class="mb-4 rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300">
          {{ conflictMessage }}
        </div>

        <div v-if="decided === 'approved'" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-6 text-center">
          <p class="text-2xl">✓</p>
          <p class="mt-2 font-semibold text-emerald-300">Orçamento aprovado!</p>
          <p class="mt-1 text-sm text-stone-400">{{ data.portal.approved_text }}</p>
        </div>

        <div v-else-if="decided === 'rejected'" class="rounded-2xl border border-red-500/20 bg-red-500/10 p-6 text-center">
          <p class="text-2xl">✕</p>
          <p class="mt-2 font-semibold text-red-300">Orçamento recusado.</p>
          <p class="mt-1 text-sm text-stone-400">{{ data.portal.rejected_text }}</p>
        </div>

        <div v-else-if="data.quote.status === 'expired'" class="rounded-2xl border border-stone-500/20 bg-stone-500/10 p-6 text-center">
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

        <div v-if="data.portal.footer_text || data.company.customer_contact_line" class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-5 text-sm text-stone-400">
          <p v-if="data.portal.footer_text">{{ data.portal.footer_text }}</p>
          <p v-if="data.company.customer_contact_line" class="mt-2 text-stone-300">Contato: {{ data.company.customer_contact_line }}</p>
        </div>
      </template>
    </div>
  </div>
</template>
