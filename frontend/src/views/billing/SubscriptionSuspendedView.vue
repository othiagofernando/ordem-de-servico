<script setup lang="ts">
import { computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useTenantRouting } from '../../composables/useTenantRouting'

const auth = useAuthStore()
const router = useRouter()
const { tenantLoginRoute, tenantRoute } = useTenantRouting()

const subscription = computed(() => auth.subscription)
const isAdmin = computed(() => auth.role === 'administrator')

async function leave() {
  await auth.logout()
  router.push(tenantLoginRoute())
}
</script>

<template>
  <div class="mx-auto flex min-h-[70vh] max-w-3xl items-center justify-center px-4">
    <div class="w-full rounded-3xl border border-amber-500/20 bg-amber-500/5 p-8 text-center shadow-2xl">
      <p class="text-xs font-semibold uppercase tracking-[0.25em] text-amber-300">Assinatura suspensa</p>
      <h1 class="mt-3 text-3xl font-semibold" style="color: var(--c-text)">Acesso temporariamente restrito</h1>
      <p class="mx-auto mt-3 max-w-2xl text-sm text-stone-400">
        <template v-if="isAdmin">
          A assinatura desta empresa está vencida ou suspensa.
          Renove o plano para reativar o acesso imediatamente.
        </template>
        <template v-else>
          O acesso está temporariamente suspenso. Entre em contato com o administrador da empresa para regularizar a assinatura.
        </template>
      </p>

      <div class="mt-6 grid gap-4 rounded-2xl border border-white/10 bg-black/20 p-5 text-left md:grid-cols-3">
        <div>
          <p class="text-xs uppercase tracking-widest text-stone-500">Plano</p>
          <p class="mt-1 text-sm text-white">{{ subscription?.plan.name ?? '—' }}</p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-widest text-stone-500">Status</p>
          <p class="mt-1 text-sm capitalize text-white">{{ subscription?.status ?? '—' }}</p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-widest text-stone-500">Validade</p>
          <p class="mt-1 text-sm text-white">
            {{ subscription?.expires_at ? new Date(subscription.expires_at).toLocaleDateString('pt-BR') : '—' }}
          </p>
        </div>
      </div>

      <div class="mt-8 flex flex-wrap justify-center gap-3">
        <RouterLink
          v-if="isAdmin"
          :to="tenantRoute('billing.plan')"
          class="rounded-xl bg-amber-400 px-5 py-2.5 text-sm font-semibold text-stone-950 hover:bg-amber-300"
        >
          Renovar assinatura
        </RouterLink>
        <button
          class="rounded-xl border border-white/10 px-4 py-2.5 text-sm text-stone-300 hover:bg-white/5"
          @click="router.go(0)"
        >
          Tentar novamente
        </button>
        <button
          class="rounded-xl border border-white/10 px-4 py-2.5 text-sm text-stone-300 hover:bg-white/5"
          @click="leave"
        >
          Sair da conta
        </button>
      </div>
    </div>
  </div>
</template>
