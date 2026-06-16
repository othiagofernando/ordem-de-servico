<script setup lang="ts">
defineProps<{
  paymentAmount: string
  paymentMethod: string
  paymentNotes: string
}>()

defineEmits<{
  updatePaymentAmount: [value: string]
  updatePaymentMethod: [value: string]
  updatePaymentNotes: [value: string]
  save: []
  cancel: []
}>()
</script>

<template>
  <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-6">
    <h2 class="mb-4 text-sm font-semibold text-emerald-300">Registrar Pagamento</h2>
    <div class="grid gap-4 sm:grid-cols-3">
      <div>
        <label class="mb-1.5 block text-sm text-stone-300">Valor (R$)</label>
        <input
          :value="paymentAmount"
          type="text"
          placeholder="0,00"
          class="input"
          @input="$emit('updatePaymentAmount', ($event.target as HTMLInputElement).value)"
        />
      </div>
      <div>
        <label class="mb-1.5 block text-sm text-stone-300">Forma de Pagamento</label>
        <select
          :value="paymentMethod"
          class="input"
          @change="$emit('updatePaymentMethod', ($event.target as HTMLSelectElement).value)"
        >
          <option value="pix">PIX</option>
          <option value="cash">Dinheiro</option>
          <option value="credit_card">Cartão de Crédito</option>
          <option value="debit_card">Cartão de Débito</option>
          <option value="bank_transfer">Transferência</option>
          <option value="other">Outro</option>
        </select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm text-stone-300">Observações</label>
        <input
          :value="paymentNotes"
          class="input"
          @input="$emit('updatePaymentNotes', ($event.target as HTMLInputElement).value)"
        />
      </div>
    </div>
    <div class="mt-3 flex gap-2">
      <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500" @click="$emit('save')">Confirmar</button>
      <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="$emit('cancel')">Cancelar</button>
    </div>
  </div>
</template>

<style scoped>
@reference "../../../style.css";

.input {
  @apply w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20;
}
</style>
