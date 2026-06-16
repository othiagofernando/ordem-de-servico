<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{
  title: string
  description: string
  confirmLabel?: string
  loading?: boolean
  error?: string
}>()

const emit = defineEmits<{
  confirm: [password: string]
  cancel: []
}>()

const password = ref('')

watch(() => props.error, () => {
  // keep password field so user can correct it
})

function submit() {
  if (!password.value || props.loading) return
  emit('confirm', password.value)
}

function cancel() {
  password.value = ''
  emit('cancel')
}
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-4 sm:items-center"
    @click.self="cancel"
  >
    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-stone-900 p-6 shadow-2xl">
      <h2 class="mb-1 text-base font-semibold text-white">{{ title }}</h2>
      <p class="mb-4 text-sm text-stone-400">{{ description }}</p>

      <div v-if="error" class="mb-3 rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300">
        {{ error }}
      </div>

      <label class="mb-1.5 block text-sm text-stone-400">Confirme sua senha</label>
      <input
        v-model="password"
        type="password"
        class="input"
        placeholder="••••••••"
        autofocus
        @keyup.enter="submit"
      />

      <div class="mt-5 flex gap-2">
        <button
          :disabled="!password || loading"
          class="flex-1 rounded-xl bg-red-700 py-2.5 text-sm font-semibold text-white transition hover:bg-red-600 disabled:opacity-50"
          @click="submit"
        >
          {{ loading ? 'Aguarde…' : (confirmLabel ?? 'Confirmar') }}
        </button>
        <button
          :disabled="loading"
          class="rounded-xl border border-white/10 px-5 py-2.5 text-sm text-stone-400 transition hover:border-white/20 hover:text-stone-200 disabled:opacity-50"
          @click="cancel"
        >
          Cancelar
        </button>
      </div>
    </div>
  </div>
</template>
