<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const router = useRouter()

if (!auth.pendingSelection) {
  router.replace({ name: 'login' })
}

async function select(uuid: string) {
  await auth.selectCompany(uuid)
  router.push({ name: 'dashboard' })
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-sm">
      <div class="mb-8 text-center">
        <span class="text-3xl font-bold text-amber-400">TechFix</span>
      </div>

      <div class="rounded-2xl border border-white/10 bg-black/30 p-8 shadow-2xl backdrop-blur">
        <h1 class="mb-2 text-xl font-semibold text-white">Selecione a empresa</h1>
        <p class="mb-6 text-sm text-stone-400">Sua conta está vinculada a mais de uma empresa.</p>

        <div class="space-y-2">
          <button
            v-for="company in auth.pendingSelection?.companies"
            :key="company.company_uuid"
            :disabled="auth.loading"
            class="flex w-full items-center justify-between rounded-xl border border-white/10 bg-white/5 px-4 py-3.5 text-left transition hover:border-amber-500/30 hover:bg-amber-500/5 disabled:opacity-50"
            @click="select(company.company_uuid)"
          >
            <div>
              <p class="text-sm font-medium text-white">{{ company.company_name }}</p>
              <p class="text-xs text-stone-500 capitalize">{{ company.role }}</p>
            </div>
            <span class="text-stone-500">→</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
