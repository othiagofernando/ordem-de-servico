<script setup lang="ts">
import type { QuoteLineItem } from '../../../api/serviceOrders'

defineProps<{
  quoteItems: QuoteLineItem[]
  quoteNotes: string
  quoteValidDays: number
  quoteTotal: number
  formatMoney: (value: number) => string
}>()

defineEmits<{
  updateQuoteNotes: [value: string]
  updateQuoteValidDays: [value: number]
  addItem: []
  removeItem: [index: number]
  updateItemDescription: [index: number, value: string]
  updateItemQuantity: [index: number, value: number]
  updateItemUnitPriceCents: [index: number, value: number]
  save: []
  cancel: []
}>()
</script>

<template>
  <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-6">
    <h2 class="mb-4 text-sm font-semibold text-amber-300">Novo Orçamento</h2>
    <div class="space-y-3">
      <div
        v-for="(item, i) in quoteItems"
        :key="i"
        class="grid gap-2 sm:grid-cols-[1fr_80px_110px_auto]"
      >
        <input
          :value="item.description"
          placeholder="Descrição do item"
          class="input"
          @input="$emit('updateItemDescription', i, ($event.target as HTMLInputElement).value)"
        />
        <input
          :value="item.quantity"
          type="number"
          min="1"
          placeholder="Qtd"
          class="input"
          @input="$emit('updateItemQuantity', i, Number(($event.target as HTMLInputElement).value))"
        />
        <input
          :value="(item.unit_price_cents / 100).toFixed(2)"
          type="number"
          min="0"
          step="0.01"
          placeholder="R$ unit."
          class="input"
          @change="$emit('updateItemUnitPriceCents', i, Math.round(parseFloat(($event.target as HTMLInputElement).value) * 100))"
        />
        <button v-if="quoteItems.length > 1" type="button" class="text-red-400 hover:text-red-300" @click="$emit('removeItem', i)">✕</button>
      </div>
      <button type="button" class="text-sm text-amber-400 hover:text-amber-300" @click="$emit('addItem')">+ Adicionar item</button>

      <div class="flex items-center justify-between rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-2.5">
        <span class="text-sm text-stone-300">Total</span>
        <span class="font-semibold text-amber-300">{{ formatMoney(quoteTotal) }}</span>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="mb-1.5 block text-sm text-stone-300">Validade (dias)</label>
          <input
            :value="quoteValidDays"
            type="number"
            min="1"
            class="input"
            @input="$emit('updateQuoteValidDays', Number(($event.target as HTMLInputElement).value))"
          />
        </div>
        <div>
          <label class="mb-1.5 block text-sm text-stone-300">Observações</label>
          <input
            :value="quoteNotes"
            class="input"
            @input="$emit('updateQuoteNotes', ($event.target as HTMLInputElement).value)"
          />
        </div>
      </div>

      <div class="flex gap-2">
        <button class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-400" @click="$emit('save')">Salvar Orçamento</button>
        <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="$emit('cancel')">Cancelar</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../../style.css";

</style>
