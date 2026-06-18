<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useTenantRouting } from '../../composables/useTenantRouting'

const route = useRoute()
const { tenantLoginRoute } = useTenantRouting()
const tenantSlug = computed(() => String(route.params.tenantSlug ?? 'empresa'))
</script>

<template>
  <div class="flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-black/30 p-10 text-center backdrop-blur">
      <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">Tenant inválido</p>
      <h1 class="mt-4 text-3xl font-semibold text-white">Empresa não encontrada</h1>
      <p class="mt-3 text-sm text-stone-400">
        Não existe uma empresa ativa vinculada ao slug <strong class="text-stone-200">{{ tenantSlug }}</strong>.
      </p>
      <RouterLink
        :to="tenantLoginRoute()"
        class="mt-8 inline-flex rounded-xl bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
      >
        Voltar ao login
      </RouterLink>
    </div>
  </div>
</template>
