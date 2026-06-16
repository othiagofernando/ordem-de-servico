<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import NavIcon from '../components/NavIcon.vue'

const auth = useAuthStore()
const router = useRouter()
const sidebarOpen = ref(false)

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}

const navLinks = [
  { name: 'dashboard', label: 'Dashboard' },
  { name: 'reports', label: 'Relatórios' },
  { name: 'service-orders', label: 'Ordens de Serviço' },
  { name: 'customers', label: 'Clientes' },
  { name: 'equipments', label: 'Equipamentos' },
  { name: 'members', label: 'Membros' },
  { name: 'checklists', label: 'Checklists' },
  { name: 'settings', label: 'Configurações' },
]
</script>

<template>
  <div class="flex min-h-screen bg-stone-950 text-stone-100">
    <!-- Sidebar -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 z-40 flex w-60 flex-col bg-stone-950 border-r border-white/[0.06] transition-transform duration-200',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        'lg:relative lg:flex lg:translate-x-0',
      ]"
    >
      <!-- Logo -->
      <div class="flex h-14 items-center border-b border-white/[0.06] px-4">
        <span class="text-base font-bold tracking-tight text-white">TechFix</span>
        <span class="ml-1 rounded bg-amber-500/20 px-1 py-0.5 text-[10px] font-semibold text-amber-400 uppercase tracking-wider">OS</span>
      </div>

      <!-- Nav -->
      <nav class="flex-1 space-y-0.5 px-3 py-3">
        <RouterLink
          v-for="link in navLinks"
          :key="link.name"
          :to="{ name: link.name }"
          class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-stone-400 transition-colors hover:bg-white/5 hover:text-stone-200"
          active-class="bg-amber-500/10 text-amber-300 hover:text-amber-300"
          @click="sidebarOpen = false"
        >
          <NavIcon :name="link.name" />
          {{ link.label }}
        </RouterLink>
      </nav>

      <!-- Rodapé: empresa + logout -->
      <div class="border-t border-white/[0.06] p-4 space-y-3">
        <div class="px-1">
          <p class="truncate text-sm font-medium text-white">{{ auth.company?.name ?? '—' }}</p>
          <p class="mt-0.5 text-xs text-stone-500 capitalize">{{ auth.role }}</p>
          <p v-if="auth.membershipCount > 1" class="text-xs text-stone-500">{{ auth.membershipCount }} empresas vinculadas</p>
        </div>
        <button
          class="w-full rounded-lg px-3 py-2 text-left text-sm text-stone-400 transition-colors hover:bg-white/5 hover:text-red-400"
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
      <!-- Header -->
      <header class="flex h-14 items-center gap-4 border-b border-white/[0.06] bg-stone-950 px-6">
        <!-- Hamburger mobile -->
        <button
          class="rounded-lg p-1.5 text-stone-400 hover:bg-white/5 hover:text-stone-200 lg:hidden"
          aria-label="Abrir menu"
          @click="sidebarOpen = !sidebarOpen"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>

        <div class="flex-1" />

        <!-- Usuário -->
        <div class="flex items-center gap-2">
          <span class="text-sm text-stone-300">{{ auth.user?.name }}</span>
          <span class="rounded bg-stone-800 px-2 py-0.5 text-xs text-stone-400 capitalize">{{ auth.role }}</span>
        </div>
      </header>

      <main class="flex-1 p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
