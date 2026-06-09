<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const sidebarOpen = ref(false)

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}

const navLinks = [
  { name: 'dashboard', label: 'Dashboard', icon: '⬜' },
  { name: 'service-orders', label: 'Ordens de Serviço', icon: '📋' },
  { name: 'customers', label: 'Clientes', icon: '👤' },
  { name: 'equipments', label: 'Equipamentos', icon: '🔧' },
  { name: 'members', label: 'Membros', icon: '👥' },
  { name: 'checklists', label: 'Checklists', icon: '📝' },
  { name: 'settings', label: 'Configurações', icon: '⚙️' },
]
</script>

<template>
  <div class="flex min-h-screen bg-stone-950 text-stone-100">
    <!-- Sidebar -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 z-40 w-64 flex-col bg-black/60 border-r border-white/10 backdrop-blur transition-transform duration-200',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        'lg:relative lg:flex lg:translate-x-0',
      ]"
    >
      <div class="flex h-16 items-center gap-2 border-b border-white/10 px-5">
        <span class="text-lg font-bold text-amber-400">TechFix</span>
        <span class="text-xs text-stone-500">OS</span>
      </div>

      <nav class="flex-1 space-y-1 px-3 py-4">
        <RouterLink
          v-for="link in navLinks"
          :key="link.name"
          :to="{ name: link.name }"
          class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-400 transition hover:bg-white/5 hover:text-stone-100"
          active-class="bg-amber-500/10 text-amber-300"
          @click="sidebarOpen = false"
        >
          <span class="text-base">{{ link.icon }}</span>
          {{ link.label }}
        </RouterLink>
      </nav>

      <div class="border-t border-white/10 p-4">
        <div class="mb-3 rounded-xl bg-white/5 px-3 py-2.5">
          <p class="text-xs text-stone-500">Empresa</p>
          <p class="mt-0.5 truncate text-sm font-medium text-stone-200">{{ auth.company?.name ?? '—' }}</p>
          <p class="text-xs text-stone-500 capitalize">{{ auth.role }}</p>
        </div>
        <button
          class="w-full rounded-xl border border-white/10 px-3 py-2 text-sm text-stone-400 transition hover:border-red-500/30 hover:text-red-400"
          @click="logout"
        >
          Sair
        </button>
      </div>
    </aside>

    <!-- Overlay mobile -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-30 bg-black/50 lg:hidden"
      @click="sidebarOpen = false"
    />

    <!-- Content -->
    <div class="flex flex-1 flex-col lg:min-w-0">
      <header class="flex h-16 items-center gap-4 border-b border-white/10 bg-black/30 px-6">
        <button
          class="rounded-lg p-2 text-stone-400 hover:bg-white/5 lg:hidden"
          @click="sidebarOpen = !sidebarOpen"
        >
          ☰
        </button>
        <div class="flex-1" />
        <span class="text-sm text-stone-400">{{ auth.user?.name }}</span>
      </header>

      <main class="flex-1 p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
