<script setup lang="ts">
import type { QuoteRevision } from '../../../api/serviceOrders'

defineProps<{
  quotes: QuoteRevision[]
  portalLinks: Record<number, string>
  quoteStatusLabels: Record<string, { label: string; cls: string }>
  formatDateTime: (value: string | null) => string
  formatMoney: (value: number) => string
}>()

defineEmits<{
  generatePortalToken: [quoteId: number]
  copyLink: [link: string]
}>()
</script>

<template>
  <div class="rounded-2xl border border-white/10 bg-white/5 p-6 lg:col-span-2">
    <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Orçamentos</h2>
    <div v-if="quotes.length === 0" class="text-sm text-stone-500">Nenhum orçamento cadastrado.</div>
    <div v-else class="space-y-4">
      <div
        v-for="q in quotes"
        :key="q.id"
        class="rounded-xl border border-white/5 bg-black/20 p-4"
      >
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <span class="text-sm font-semibold text-stone-200">Revisão {{ q.revision_number }}</span>
          <div class="flex items-center gap-2">
            <span :class="quoteStatusLabels[q.status]?.cls" class="rounded-full px-2.5 py-0.5 text-xs font-semibold">
              {{ quoteStatusLabels[q.status]?.label ?? q.status }}
            </span>
            <button
              v-if="q.status === 'pending'"
              class="rounded-lg border border-amber-500/30 px-2.5 py-1 text-xs text-amber-400 hover:bg-amber-500/10"
              @click="$emit('generatePortalToken', q.id)"
            >
              Gerar Link do Portal
            </button>
          </div>
        </div>
        <div v-if="portalLinks[q.id]" class="mb-3 flex items-center gap-2 rounded-lg border border-amber-500/20 bg-amber-500/5 px-3 py-2">
          <span class="flex-1 truncate font-mono text-xs text-amber-300">{{ portalLinks[q.id] }}</span>
          <button class="text-xs text-stone-400 hover:text-stone-200" @click="$emit('copyLink', portalLinks[q.id])">Copiar</button>
        </div>
        <div class="space-y-1">
          <div
            v-for="(item, i) in q.line_items"
            :key="i"
            class="flex items-center justify-between text-sm"
          >
            <span class="text-stone-400">{{ item.quantity }}x {{ item.description }}</span>
            <span class="text-stone-300">{{ formatMoney(item.unit_price_cents * item.quantity) }}</span>
          </div>
        </div>
        <div class="mt-3 flex justify-between border-t border-white/5 pt-2">
          <span class="text-sm font-semibold text-stone-300">Total</span>
          <span class="font-semibold text-amber-300">{{ formatMoney(q.total_cents) }}</span>
        </div>
        <div class="mt-2 flex flex-wrap gap-3 text-xs text-stone-500">
          <span>Válido até {{ formatDateTime(q.valid_until) }}</span>
          <span v-if="q.decisioned_at">Decisão: {{ formatDateTime(q.decisioned_at) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
