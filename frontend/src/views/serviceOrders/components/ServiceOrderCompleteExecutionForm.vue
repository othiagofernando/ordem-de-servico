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
    <div class="space-y-3">
      <div v-for="(r, i) in testResults" :key="i" class="rounded-lg border border-white/10 bg-white/5 p-3 space-y-2">
        <!-- Descrição do teste -->
        <input
          :value="r.label"
          placeholder="Descrição do teste (ex: Tela acende normalmente, Bateria carrega, WiFi conecta)"
          class="input w-full"
          @input="$emit('updateTestLabel', i, ($event.target as HTMLInputElement).value)"
        />

        <!-- Resultado e botão remover -->
        <div class="flex items-center gap-2">
          <select
            :value="String(r.passed)"
            class="input flex-1"
            @change="$emit('updateTestPassed', i, ($event.target as HTMLSelectElement).value === 'true')"
          >
            <option value="true">✓ Passou</option>
            <option value="false">✕ Falhou</option>
          </select>
          <button v-if="testResults.length > 1" type="button" class="text-red-400 hover:text-red-300 px-2 py-1" @click="$emit('removeTest', i)">✕ Remover</button>
        </div>
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

</style>
