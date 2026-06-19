<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useThemeStore } from '../stores/theme'
import NavIcon from '../components/NavIcon.vue'
import { useTenantRouting } from '../composables/useTenantRouting'

const auth = useAuthStore()
const theme = useThemeStore()
const router = useRouter()
const sidebarOpen = ref(false)
const { tenantRoute, tenantLoginRoute } = useTenantRouting()

const accountRoute = computed(() => {
  if (!auth.isAuthenticated) {
    return { name: 'login' }
  }
  return auth.isSuperAdmin
    ? { name: 'account.global' as const }
    : tenantRoute('account')
})

async function logout() {
  await auth.logout()
  try {
    await router.push(tenantLoginRoute() || { name: 'login' })
  } catch {
    await router.push({ name: 'login' })
  }
}

async function exitImpersonation() {
  await auth.stopImpersonation()
  await router.push({ name: 'admin' })
}

const navLinks = computed(() => {
  if (!auth.isAuthenticated) {
    return []
  }
  return auth.isSuperAdmin
    ? [
        { to: { name: 'admin' }, label: 'Resumo' },
        { to: { name: 'admin.alerts' }, label: 'Alertas' },
        { to: { name: 'admin.companies' }, label: 'Empresas' },
        { to: { name: 'admin.subscriptions' }, label: 'Assinaturas' },
        { to: { name: 'admin.billing' }, label: 'Cobranças' },
        { to: { name: 'admin.plans' }, label: 'Planos' },
        { to: { name: 'admin.audit' }, label: 'Auditoria' },
        ...(auth.adminRole === 'super_admin'
          ? [{ to: { name: 'admin.users' }, label: 'Usuários' }]
          : []),
      ]
    : [
        { to: tenantRoute('dashboard'), label: 'Dashboard' },
        { to: tenantRoute('reports'), label: 'Relatórios' },
        { to: tenantRoute('service-orders'), label: 'Ordens de Serviço' },
        { to: tenantRoute('customers'), label: 'Clientes' },
        { to: tenantRoute('equipments'), label: 'Equipamentos' },
        { to: tenantRoute('members'), label: 'Usuários' },
        ...(['administrator'].includes(auth.role ?? '') ? [{ to: tenantRoute('roles'), label: 'Perfis' }] : []),
        { to: tenantRoute('checklists'), label: 'Checklists' },
        ...(['administrator', 'manager'].includes(auth.role ?? '') ? [{ to: tenantRoute('audit'), label: 'Auditoria' }] : []),
        { to: tenantRoute('settings'), label: 'Configurações' },
      ]
})
</script>

<template>
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside
      :class="[
        'app-sidebar fixed inset-y-0 left-0 z-40 flex w-60 flex-col transition-transform duration-200',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        'lg:relative lg:flex lg:translate-x-0',
      ]"
    >
      <!-- Logo -->
      <div class="sidebar-border-b flex h-14 items-center px-4">
        <span class="text-sm font-bold tracking-tight" style="color: var(--c-text)">TechFix</span>
        <span class="app-badge ml-1.5">OS</span>
      </div>

      <!-- Nav -->
      <nav class="flex-1 space-y-0.5 px-2 py-3">
        <RouterLink
          v-for="link in navLinks"
          :key="link.label"
          :to="link.to"
          class="nav-link"
          active-class="nav-link-active"
          @click="sidebarOpen = false"
        >
          <NavIcon :name="String(link.to.name)" />
          {{ link.label }}
        </RouterLink>
      </nav>

      <!-- Rodapé: conta + logout -->
      <div class="sidebar-border-t space-y-1 p-3">
        <RouterLink
          :to="accountRoute"
          class="nav-link !gap-2.5"
          active-class="nav-link-active"
          @click="sidebarOpen = false"
        >
          <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-[11px] font-semibold text-amber-300">
            {{ (auth.user?.name ?? '?')[0].toUpperCase() }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-medium" style="color: var(--c-text)">{{ auth.user?.name ?? '—' }}</p>
            <p class="text-[11px] capitalize" style="color: var(--c-text-muted)">{{ auth.role }}</p>
          </div>
        </RouterLink>
        <div class="truncate px-3 text-[11px]" style="color: var(--c-text-dim)">
          {{ auth.isSuperAdmin ? 'Escopo global' : auth.company?.name }}
        </div>
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
          class="rounded-lg p-1.5 transition-colors lg:hidden"
          style="color: var(--c-text-muted)"
          aria-label="Abrir menu"
          @click="sidebarOpen = !sidebarOpen"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>
        <div class="flex-1" />

        <!-- Theme toggle -->
        <button
          class="theme-toggle"
          :title="theme.isDark ? 'Mudar para modo claro' : 'Mudar para modo escuro'"
          @click="theme.toggle()"
        >
          <!-- Sun (visible in dark mode → click to go light) -->
          <svg v-if="theme.isDark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4.5 w-4.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
          </svg>
          <!-- Moon (visible in light mode → click to go dark) -->
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4.5 w-4.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
          </svg>
        </button>

        <RouterLink :to="accountRoute" class="header-account-link">
          <span>{{ auth.user?.name }}</span>
          <span class="role-badge capitalize">{{ auth.isSuperAdmin ? (auth.adminRole ?? 'super_admin') : auth.role }}</span>
        </RouterLink>
      </header>

      <main class="flex-1 p-6">
        <!-- Banner de impersonação -->
        <div
          v-if="auth.isImpersonating && auth.impersonation"
          class="mb-6 flex items-center justify-between gap-4 rounded-2xl border border-violet-500/30 bg-violet-500/10 px-4 py-3"
        >
          <p class="text-sm text-violet-200">
            <strong class="font-semibold">Modo impersonação:</strong>
            você está acessando como <strong>{{ auth.impersonation.userName }}</strong> em <strong>{{ auth.impersonation.companyName }}</strong>.
          </p>
          <button
            type="button"
            class="shrink-0 rounded-lg border border-violet-500/30 bg-violet-500/10 px-3 py-1.5 text-xs font-semibold text-violet-300 transition hover:bg-violet-500/20"
            @click="exitImpersonation"
          >
            Encerrar impersonação
          </button>
        </div>

        <div
          v-if="!auth.isSuperAdmin && auth.subscription && auth.subscription.days_until_expiration <= 7"
          class="mb-6 rounded-2xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-200"
        >
          <strong class="font-semibold">Assinatura perto do vencimento.</strong>
          Vence em {{ auth.subscription.days_until_expiration }} dia(s).
          <span v-if="auth.subscription.plan.name">Plano atual: {{ auth.subscription.plan.name }}.</span>
        </div>
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped>
@reference "../style.css";

.app-sidebar {
  background: var(--c-sidebar);
  border-right: 1px solid var(--c-sidebar-border);
}

.sidebar-border-b {
  border-bottom: 1px solid var(--c-sidebar-border);
}

.sidebar-border-t {
  border-top: 1px solid var(--c-sidebar-border);
}

.app-badge {
  @apply rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider;
  background: rgba(251, 146, 60, 0.15);
  color: #fb923c;
}

.nav-link {
  @apply flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors;
  color: var(--c-text-muted);
}

.nav-link:hover {
  background: var(--c-nav-hover);
  color: var(--c-text);
}

.nav-link-active {
  background: var(--c-nav-active) !important;
  color: #fbbf24 !important;
}

.nav-link-active:hover {
  color: #fbbf24 !important;
}

.app-header {
  background: var(--c-header);
  border-bottom: 1px solid var(--c-sidebar-border);
  backdrop-filter: blur(8px);
  position: sticky;
  top: 0;
  z-index: 10;
}

.theme-toggle {
  @apply flex h-8 w-8 items-center justify-center rounded-lg transition-colors;
  color: var(--c-text-muted);
}

.theme-toggle:hover {
  background: var(--c-nav-hover);
  color: var(--c-text);
}

.header-account-link {
  @apply flex items-center gap-2 rounded-lg px-2 py-1 text-sm transition-colors;
  color: var(--c-text-muted);
}

.header-account-link:hover {
  color: var(--c-text);
}

.role-badge {
  @apply rounded px-1.5 py-0.5 text-[11px];
  background: var(--c-surface-hover);
  color: var(--c-text-muted);
}

html.light .role-badge {
  background: rgba(0, 0, 0, 0.07);
}
</style>
