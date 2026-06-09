<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { ApiError } from '../../api/client'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const status = ref<'loading' | 'success' | 'error'>('loading')
const errorMessage = ref('')

onMounted(async () => {
  const token = route.params.token as string
  if (!token) {
    status.value = 'error'
    errorMessage.value = 'Link inválido.'
    return
  }
  try {
    await auth.loginWithMagicLink(token)
    status.value = 'success'
    setTimeout(() => router.push({ name: 'dashboard' }), 1200)
  } catch (e) {
    status.value = 'error'
    if (e instanceof ApiError) {
      if (e.status === 422) {
        errorMessage.value = 'Este link de acesso é inválido, já foi utilizado ou expirou.'
      } else if (e.status === 403) {
        errorMessage.value = 'Seu acesso foi revogado. Entre em contato com o administrador.'
      } else {
        errorMessage.value = (e.data as { message?: string }).message ?? 'Erro ao processar o link.'
      }
    } else {
      errorMessage.value = 'Erro ao conectar com o servidor.'
    }
  }
})
</script>

<template>
  <div class="flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-sm text-center">
      <div class="mb-8">
        <span class="text-3xl font-bold text-amber-400">TechFix</span>
      </div>

      <!-- Carregando -->
      <div v-if="status === 'loading'" class="rounded-2xl border border-white/10 bg-black/30 p-10 backdrop-blur">
        <div class="mb-4 text-4xl">⏳</div>
        <p class="text-stone-300">Autenticando…</p>
      </div>

      <!-- Sucesso -->
      <div v-else-if="status === 'success'" class="rounded-2xl border border-emerald-500/20 bg-black/30 p-10 backdrop-blur">
        <div class="mb-4 text-4xl">✅</div>
        <p class="text-lg font-semibold text-white">Acesso confirmado!</p>
        <p class="mt-1 text-sm text-stone-400">Redirecionando…</p>
      </div>

      <!-- Erro -->
      <div v-else class="rounded-2xl border border-red-500/20 bg-black/30 p-10 backdrop-blur">
        <div class="mb-4 text-4xl">❌</div>
        <p class="text-base font-semibold text-white">Link inválido</p>
        <p class="mt-2 text-sm text-stone-400">{{ errorMessage }}</p>
        <RouterLink
          :to="{ name: 'login' }"
          class="mt-6 inline-block rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-stone-950 hover:bg-amber-400"
        >
          Voltar ao login
        </RouterLink>
      </div>
    </div>
  </div>
</template>
