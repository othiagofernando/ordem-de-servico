<script setup lang="ts">
import type { FinalTestResult } from '../../../api/serviceOrders'

defineProps<{
  testResults: FinalTestResult[]
}>()

defineEmits<{
  addTest: []
  removeTest: [index: number]
  updateTestLabel: [index: number, value: string]
  updateTestPassed: [index: number, value: boolean]
  save: []
  cancel: []
}>()
</script>

<template>
  <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-6">
    <h2 class="mb-4 text-sm font-semibold text-emerald-300">Concluir Execução — Testes Finais</h2>
    <p class="mb-4 text-sm text-stone-400">Registre os resultados dos testes realizados antes de marcar como tecnicamente concluída.</p>
    <div class="space-y-2">
      <div v-for="(r, i) in testResults" :key="i" class="flex items-center gap-2">
        <input
          :value="r.label"
          placeholder="Descrição do teste (ex: Liga normalmente)"
          class="input flex-1"
          @input="$emit('updateTestLabel', i, ($event.target as HTMLInputElement).value)"
        />
        <select
          :value="String(r.passed)"
          class="input w-32"
          @change="$emit('updateTestPassed', i, ($event.target as HTMLSelectElement).value === 'true')"
        >
          <option value="true">Passou</option>
          <option value="false">Falhou</option>
        </select>
        <button v-if="testResults.length > 1" type="button" class="text-red-400 hover:text-red-300" @click="$emit('removeTest', i)">✕</button>
      </div>
    </div>
    <button type="button" class="mt-2 text-sm text-emerald-400 hover:text-emerald-300" @click="$emit('addTest')">+ Adicionar teste</button>
    <div class="mt-4 flex gap-2">
      <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500" @click="$emit('save')">Concluir</button>
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
