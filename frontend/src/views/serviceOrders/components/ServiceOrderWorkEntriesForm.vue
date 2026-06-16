<script setup lang="ts">
import type { WorkEntry } from '../../../api/serviceOrders'

defineProps<{
  workServices: WorkEntry[]
  workParts: WorkEntry[]
}>()

defineEmits<{
  addService: []
  removeService: [index: number]
  updateServiceDescription: [index: number, value: string]
  updateServiceQuantity: [index: number, value: number]
  addPart: []
  removePart: [index: number]
  updatePartDescription: [index: number, value: string]
  updatePartQuantity: [index: number, value: number]
  save: []
  cancel: []
}>()
</script>

<template>
  <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-6">
    <h2 class="mb-4 text-sm font-semibold text-amber-300">Registrar Serviços e Peças</h2>
    <div class="space-y-4">
      <div>
        <div class="mb-2 flex items-center justify-between">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-400">Serviços</p>
          <button type="button" class="text-xs text-amber-400 hover:text-amber-300" @click="$emit('addService')">+ Adicionar</button>
        </div>
        <div class="space-y-2">
          <div v-for="(s, i) in workServices" :key="`svc-${i}`" class="flex items-center gap-2">
            <input
              :value="s.description"
              placeholder="Descrição do serviço"
              class="input flex-1"
              @input="$emit('updateServiceDescription', i, ($event.target as HTMLInputElement).value)"
            />
            <input
              :value="s.quantity"
              type="number"
              min="1"
              class="input w-20"
              @input="$emit('updateServiceQuantity', i, Number(($event.target as HTMLInputElement).value))"
            />
            <button v-if="workServices.length > 1" type="button" class="text-red-400 hover:text-red-300" @click="$emit('removeService', i)">✕</button>
          </div>
        </div>
      </div>
      <div>
        <div class="mb-2 flex items-center justify-between">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-400">Peças</p>
          <button type="button" class="text-xs text-amber-400 hover:text-amber-300" @click="$emit('addPart')">+ Adicionar</button>
        </div>
        <div class="space-y-2">
          <div v-for="(p, i) in workParts" :key="`part-${i}`" class="flex items-center gap-2">
            <input
              :value="p.description"
              placeholder="Descrição da peça"
              class="input flex-1"
              @input="$emit('updatePartDescription', i, ($event.target as HTMLInputElement).value)"
            />
            <input
              :value="p.quantity"
              type="number"
              min="1"
              class="input w-20"
              @input="$emit('updatePartQuantity', i, Number(($event.target as HTMLInputElement).value))"
            />
            <button type="button" class="text-red-400 hover:text-red-300" @click="$emit('removePart', i)">✕</button>
          </div>
        </div>
      </div>
      <div class="flex gap-2">
        <button class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-400" @click="$emit('save')">Salvar</button>
        <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="$emit('cancel')">Cancelar</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../../style.css";

.input {
  @apply w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20;
}
</style>
