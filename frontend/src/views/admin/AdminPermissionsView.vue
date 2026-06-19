<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  type AdminUser,
  type AdminGlobalRole,
  listAdminUsers,
  listAdminRoles,
  ADMIN_ROLE_LABELS,
} from '../../api/admin'

const users = ref<AdminUser[]>([])
const globalRoles = ref<AdminGlobalRole[]>([])
const loading = ref(true)
const error = ref('')

const activeTab = ref<'matrix' | string>('matrix')

const ADMIN_SECTIONS = [
  { value: 'overview',         label: 'Resumo' },
  { value: 'alerts',           label: 'Alertas' },
  { value: 'companies',        label: 'Empresas' },
  { value: 'companies.manage', label: 'Empresas Gestão' },
  { value: 'subscriptions',    label: 'Assinaturas' },
  { value: 'billing',          label: 'Cobranças' },
  { value: 'plans',            label: 'Planos' },
  { value: 'audit',            label: 'Auditoria' },
]

// Fixed role → sections mapping
const FIXED_ROLE_SECTIONS: Record<string, string[]> = {
  super_admin: ADMIN_SECTIONS.map((s) => s.value),
  support:     ['overview', 'alerts', 'companies', 'companies.manage', 'subscriptions', 'audit'],
  billing:     ['overview', 'billing', 'subscriptions'],
  plans:       ['overview', 'plans'],
  viewer:      ['overview', 'companies', 'subscriptions', 'audit'],
}

function userSections(user: AdminUser): string[] {
  if (user.admin_role_id) {
    const role = globalRoles.value.find((r) => r.id === user.admin_role_id)
    return role?.permissions ?? []
  }
  return FIXED_ROLE_SECTIONS[user.admin_role ?? 'super_admin'] ?? []
}

function hasSection(user: AdminUser, section: string): boolean {
  return userSections(user).includes(section)
}

function dotClass(user: AdminUser, section: string): string {
  if (!hasSection(user, section)) return ''
  if (user.admin_role_id) return 'bg-indigo-400'
  return 'bg-amber-400'
}

function userRoleLabel(user: AdminUser): string {
  if (user.admin_role_id) {
    const role = globalRoles.value.find((r) => r.id === user.admin_role_id)
    return role?.name ?? 'Personalizado'
  }
  return ADMIN_ROLE_LABELS[user.admin_role ?? 'super_admin'] ?? user.admin_role ?? '—'
}

const tabs = computed(() => [
  { value: 'matrix', label: 'Matriz de acesso' },
  ...ADMIN_SECTIONS.map((s) => ({ value: s.value, label: s.label })),
])

function usersWithSection(section: string): AdminUser[] {
  return users.value.filter((u) => hasSection(u, section))
}

onMounted(async () => {
  try {
    const [usersRes, rolesRes] = await Promise.all([listAdminUsers(), listAdminRoles()])
    users.value = usersRes.data
    globalRoles.value = rolesRes.data
  } catch {
    error.value = 'Erro ao carregar dados de permissões.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">

    <!-- ── Cabeçalho ──────────────────────────────────────── -->
    <div>
      <h1 class="text-2xl font-semibold text-white">Mapa de Permissões</h1>
      <p class="mt-1 text-sm text-stone-400">
        Visualize quais seções do painel cada usuário admin pode acessar.
      </p>
    </div>

    <!-- ── Legenda ─────────────────────────────────────────── -->
    <div class="flex flex-wrap items-center gap-4 text-xs text-stone-400">
      <span class="flex items-center gap-1.5">
        <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-400"></span>
        Perfil fixo
      </span>
      <span class="flex items-center gap-1.5">
        <span class="inline-block h-2.5 w-2.5 rounded-full bg-indigo-400"></span>
        Perfil personalizado
      </span>
      <span class="flex items-center gap-1.5">
        <span class="inline-block h-2.5 w-2.5 rounded-full bg-white/10"></span>
        Sem acesso
      </span>
    </div>

    <!-- ── Erro ───────────────────────────────────────────── -->
    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <!-- ── Loading ────────────────────────────────────────── -->
    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando permissões…</div>

    <template v-else>
      <!-- ── Tabs ───────────────────────────────────────────── -->
      <div class="flex flex-wrap gap-2">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          type="button"
          :class="[
            'rounded-lg border px-3 py-1.5 text-xs font-medium transition',
            activeTab === tab.value
              ? 'border-amber-500/40 bg-amber-500/10 text-amber-300'
              : 'border-white/10 bg-white/5 text-stone-400 hover:border-white/20 hover:text-stone-200',
          ]"
          @click="activeTab = tab.value"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- ── Visão: Matriz ──────────────────────────────────── -->
      <div v-if="activeTab === 'matrix'" class="overflow-x-auto">
        <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/5">
          <table class="min-w-full text-sm">
            <thead class="bg-black/20 text-xs uppercase tracking-widest text-stone-500">
              <tr>
                <th class="px-4 py-3 text-left">Usuário</th>
                <th class="px-4 py-3 text-left">Perfil</th>
                <th
                  v-for="section in ADMIN_SECTIONS"
                  :key="section.value"
                  class="px-2 py-3 text-center"
                  :title="section.label"
                >
                  <span class="block max-w-[60px] truncate text-[10px]">{{ section.label }}</span>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="user in users" :key="user.id" class="hover:bg-white/5">
                <td class="px-4 py-3">
                  <div class="font-medium text-white">{{ user.name }}</div>
                  <div class="text-xs text-stone-500">{{ user.email }}</div>
                </td>
                <td class="px-4 py-3">
                  <span class="text-xs text-stone-400">{{ userRoleLabel(user) }}</span>
                  <span v-if="user.admin_role_id" class="ml-1 text-[10px] text-indigo-400">Custom</span>
                </td>
                <td
                  v-for="section in ADMIN_SECTIONS"
                  :key="section.value"
                  class="px-2 py-3 text-center"
                >
                  <span
                    class="inline-block h-2.5 w-2.5 rounded-full"
                    :class="dotClass(user, section.value) || 'bg-white/10'"
                  ></span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Visão: Por seção ───────────────────────────────── -->
      <div v-else class="space-y-4">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <h2 class="mb-4 text-base font-semibold text-white">
            Usuários com acesso a:
            <span class="ml-1 text-amber-300">{{ ADMIN_SECTIONS.find((s) => s.value === activeTab)?.label }}</span>
          </h2>

          <div v-if="usersWithSection(activeTab).length === 0" class="py-8 text-center text-sm text-stone-500">
            Nenhum usuário tem acesso a esta seção.
          </div>

          <div v-else class="overflow-hidden rounded-2xl border border-white/10">
            <table class="min-w-full divide-y divide-white/5 text-sm">
              <thead class="bg-black/20 text-xs uppercase tracking-widest text-stone-500">
                <tr>
                  <th class="px-4 py-3 text-left">Usuário</th>
                  <th class="px-4 py-3 text-left">E-mail</th>
                  <th class="px-4 py-3 text-left">Perfil</th>
                  <th class="px-4 py-3 text-left">Tipo</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-white/5">
                <tr v-for="user in usersWithSection(activeTab)" :key="user.id" class="hover:bg-white/5">
                  <td class="px-4 py-3 font-medium text-white">{{ user.name }}</td>
                  <td class="px-4 py-3 text-stone-400">{{ user.email }}</td>
                  <td class="px-4 py-3 text-stone-400">{{ userRoleLabel(user) }}</td>
                  <td class="px-4 py-3">
                    <span
                      v-if="user.admin_role_id"
                      class="inline-flex items-center gap-1 text-xs font-medium text-indigo-300"
                    >
                      <span class="inline-block h-2 w-2 rounded-full bg-indigo-400"></span>
                      Personalizado
                    </span>
                    <span v-else class="inline-flex items-center gap-1 text-xs font-medium text-amber-300">
                      <span class="inline-block h-2 w-2 rounded-full bg-amber-400"></span>
                      Fixo
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>

  </div>
</template>
