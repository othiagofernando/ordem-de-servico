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
  <div class="flex min-h-screen text-stone-100">
    <!-- Sidebar -->
    <aside
      :class="[
        'app-sidebar fixed inset-y-0 left-0 z-40 flex w-60 flex-col transition-transform duration-200',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        'lg:relative lg:flex lg:translate-x-0',
      ]"
    >
      <!-- Logo -->
      <div class="app-sidebar-border-b flex h-14 items-center px-4">
        <span class="text-sm font-bold tracking-tight text-white">TechFix</span>
        <span class="app-badge ml-1.5">OS</span>
      </div>

      <!-- Nav -->
      <nav class="flex-1 space-y-0.5 px-2 py-3">
        <RouterLink
          v-for="link in navLinks"
          :key="link.name"
          :to="{ name: link.name }"
          class="nav-link"
          active-class="nav-link-active"
          @click="sidebarOpen = false"
        >
          <NavIcon :name="link.name" />
          {{ link.label }}
        </RouterLink>
      </nav>

      <!-- Rodapé: conta + logout -->
      <div class="app-sidebar-border-t space-y-1 p-3">
        <RouterLink
          :to="{ name: 'account' }"
          class="nav-link !gap-2.5"
          active-class="nav-link-active"
          @click="sidebarOpen = false"
        >
          <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-[11px] font-semibold text-amber-300">
            {{ (auth.user?.name ?? '?')[0].toUpperCase() }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-medium text-stone-300">{{ auth.user?.name ?? '—' }}</p>
            <p class="text-[11px] capitalize text-stone-500">{{ auth.role }}</p>
          </div>
        </RouterLink>
        <div class="truncate px-3 text-[11px] text-stone-600">{{ auth.company?.name }}</div>
        <button
          class="nav-link w-full text-left hover:!text-red-400"
          @click="logout"
        >
          Sair da conta
        </button>
      </div>
    </aside>

    <!-- Overlay mobile -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-30 bg-black/40 lg:hidden"
      @click="sidebarOpen = false"
    />

    <!-- Content -->
    <div class="flex flex-1 flex-col lg:min-w-0">
      <!-- Header -->
      <header class="app-header flex h-14 items-center gap-4 px-6">
        <!-- Hamburger mobile -->
        <button
          class="rounded-lg p-1.5 text-stone-500 transition-colors hover:text-stone-200 lg:hidden"
          aria-label="Abrir menu"
          @click="sidebarOpen = !sidebarOpen"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>
        <div class="flex-1" />
        <RouterLink :to="{ name: 'account' }" class="flex items-center gap-2 rounded-lg px-2 py-1 text-sm transition-colors hover:text-stone-200">
          <span class="text-stone-400">{{ auth.user?.name }}</span>
          <span class="app-role-badge capitalize">{{ auth.role }}</span>
        </RouterLink>
      </header>

      <main class="flex-1 p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.app-sidebar {
  background: #111113;
  border-right: 1px solid rgba(255, 255, 255, 0.06);
}

.app-sidebar-border-b {
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.app-sidebar-border-t {
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.app-badge {
  @apply rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider;
  background: rgba(251, 146, 60, 0.15);
  color: #fb923c;
}

.nav-link {
  @apply flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-stone-500 transition-colors;
}

.nav-link:hover {
  background: rgba(255, 255, 255, 0.05);
  @apply text-stone-200;
}

.nav-link-active {
  background: rgba(251, 146, 60, 0.08) !important;
  @apply text-amber-300;
}

.nav-link-active:hover {
  @apply text-amber-300;
}

.app-header {
  background: rgba(17, 17, 19, 0.75);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  backdrop-filter: blur(8px);
  position: sticky;
  top: 0;
  z-index: 10;
}

.app-role-badge {
  @apply rounded px-1.5 py-0.5 text-[11px];
  background: rgba(255, 255, 255, 0.07);
  color: #78716c;
}
</style>
