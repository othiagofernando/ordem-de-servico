<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  ADMIN_ROLE_LABELS,
  type AdminUser,
  type AdminGlobalRole,
  createAdminUser,
  listAdminUsers,
  listAdminRoles,
  revokeAdminUser,
  updateAdminUser,
} from '../../api/admin'
import { ApiError } from '../../api/client'
import { useAuthStore } from '../../stores/auth'
import { useSortable } from '../../composables/useSortable'
import ThSort from '../../components/ThSort.vue'
import AppPagination from '../../components/AppPagination.vue'

const auth = useAuthStore()
const users = ref<AdminUser[]>([])
const globalRoles = ref<AdminGlobalRole[]>([])
const loading = ref(true)
const error = ref('')
const search = ref('')
const roleFilter = ref('')

const page = ref(1)
const perPage = ref(15)

// ── resumo ────────────────────────────────────────────────
const summary = computed(() => ({
  total: users.value.length,
  super_admins: users.value.filter((u) => u.admin_role === 'super_admin').length,
  others: users.value.filter((u) => u.admin_role !== 'super_admin').length,
}))

// ── filtragem ─────────────────────────────────────────────
const filteredUsers = computed(() => {
  let result = users.value
  const q = search.value.toLowerCase().trim()
  if (q) result = result.filter((u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q))
  if (roleFilter.value) result = result.filter((u) => u.admin_role === roleFilter.value)
  return result
})

// ── ordenação ─────────────────────────────────────────────
const { sorted: sortedUsers, toggleSort, sortState } = useSortable(filteredUsers, {
  name:       (u) => u.name,
  admin_role: (u) => displayRoleLabel(u),
  created_at: (u) => u.created_at,
})

// ── paginação ─────────────────────────────────────────────
const paginationMeta = computed(() => {
  const total = sortedUsers.value.length
  const lastPage = Math.ceil(total / perPage.value) || 1
  const from = (page.value - 1) * perPage.value + 1
  const to = Math.min(page.value * perPage.value, total)
  return {
    total,
    per_page: perPage.value,
    current_page: page.value,
    last_page: lastPage,
    from: total === 0 ? 0 : from,
    to,
  }
})

const paginatedUsers = computed(() => {
  const start = (page.value - 1) * perPage.value
  return sortedUsers.value.slice(start, start + perPage.value)
})

// ── modal criar/editar ───────────────────────────────────
const showModal = ref(false)
const modalMode = ref<'create' | 'edit'>('create')
const selectedUser = ref<AdminUser | null>(null)
// roleType: 'fixed' | 'custom'
const form = ref({
  name: '',
  email: '',
  password: '',
  roleType: 'fixed' as 'fixed' | 'custom',
  admin_role: 'support',
  admin_role_id: null as number | null,
})
const formLoading = ref(false)
const formError = ref('')

// ── modal revogar ────────────────────────────────────────
const showRevokeConfirm = ref(false)
const revokeTarget = ref<AdminUser | null>(null)
const revokeLoading = ref(false)
const revokeError = ref('')

const FIXED_ROLES = Object.entries(ADMIN_ROLE_LABELS).map(([value, label]) => ({ value, label }))

const ROLE_BADGE: Record<string, string> = {
  super_admin: 'border-red-500/30 bg-red-500/15 text-red-300',
  support:     'border-sky-500/30 bg-sky-500/15 text-sky-300',
  billing:     'border-emerald-500/30 bg-emerald-500/15 text-emerald-300',
  plans:       'border-violet-500/30 bg-violet-500/15 text-violet-300',
  viewer:      'border-stone-500/30 bg-stone-500/15 text-stone-300',
}

const ROLE_DESC: Record<string, string> = {
  super_admin: 'Acesso total ao painel. Pode gerenciar outros admins.',
  support:     'Acesso a empresas, auditoria e impersonação.',
  billing:     'Acesso a cobranças e assinaturas.',
  plans:       'Acesso a planos e preços.',
  viewer:      'Leitura de todos os dados, sem edição.',
}

function roleBadge(role: string | null) { return ROLE_BADGE[role ?? ''] ?? 'border-indigo-500/30 bg-indigo-500/15 text-indigo-300' }
function roleLabel(role: string | null) { return ADMIN_ROLE_LABELS[role ?? ''] ?? role ?? '—' }
function roleDesc(role: string)  { return ROLE_DESC[role] ?? '' }

function displayRoleLabel(user: AdminUser): string {
  if (user.admin_role_id && user.admin_role_name) return user.admin_role_name
  return roleLabel(user.admin_role)
}

function displayRoleBadge(user: AdminUser): string {
  if (user.admin_role_id) return 'border-indigo-500/30 bg-indigo-500/15 text-indigo-300'
  return roleBadge(user.admin_role)
}

function openCreate() {
  modalMode.value = 'create'
  selectedUser.value = null
  form.value = { name: '', email: '', password: '', roleType: 'fixed', admin_role: 'support', admin_role_id: null }
  formError.value = ''
  showModal.value = true
}

function openEdit(user: AdminUser) {
  modalMode.value = 'edit'
  selectedUser.value = user
  const isCustom = user.admin_role_id !== null
  form.value = {
    name: user.name,
    email: user.email,
    password: '',
    roleType: isCustom ? 'custom' : 'fixed',
    admin_role: user.admin_role ?? 'support',
    admin_role_id: user.admin_role_id,
  }
  formError.value = ''
  showModal.value = true
}

async function submitForm() {
  formError.value = ''
  formLoading.value = true
  try {
    if (modalMode.value === 'create') {
      const payload: Parameters<typeof createAdminUser>[0] = {
        name: form.value.name,
        email: form.value.email,
        password: form.value.password,
      }
      if (form.value.roleType === 'custom') {
        payload.admin_role_id = form.value.admin_role_id
        payload.admin_role = null
      } else {
        payload.admin_role = form.value.admin_role
        payload.admin_role_id = null
      }
      const { data } = await createAdminUser(payload)
      users.value.push(data)
    } else if (selectedUser.value) {
      const payload: Parameters<typeof updateAdminUser>[1] = { name: form.value.name }
      if (form.value.roleType === 'custom') {
        payload.admin_role_id = form.value.admin_role_id
        payload.admin_role = null
      } else {
        payload.admin_role = form.value.admin_role
        payload.admin_role_id = null
      }
      if (form.value.password) payload.password = form.value.password
      const { data } = await updateAdminUser(selectedUser.value.id, payload)
      const idx = users.value.findIndex((u) => u.id === data.id)
      if (idx !== -1) users.value[idx] = data
    }
    showModal.value = false
  } catch (e) {
    formError.value = e instanceof ApiError ? e.message : 'Erro inesperado. Tente novamente.'
  } finally {
    formLoading.value = false
  }
}

function confirmRevoke(user: AdminUser) {
  revokeTarget.value = user
  revokeError.value = ''
  showRevokeConfirm.value = true
}

async function doRevoke() {
  if (!revokeTarget.value) return
  revokeLoading.value = true
  revokeError.value = ''
  try {
    await revokeAdminUser(revokeTarget.value.id)
    users.value = users.value.filter((u) => u.id !== revokeTarget.value!.id)
    showRevokeConfirm.value = false
  } catch (e) {
    revokeError.value = e instanceof ApiError ? e.message : 'Erro inesperado. Tente novamente.'
  } finally {
    revokeLoading.value = false
  }
}

function formatDate(iso: string | null) {
  if (!iso) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(new Date(iso))
}

onMounted(async () => {
  try {
    const [usersRes, rolesRes] = await Promise.all([listAdminUsers(), listAdminRoles()])
    users.value = usersRes.data
    globalRoles.value = rolesRes.data
  } catch {
    error.value = 'Erro ao carregar usuários administrativos.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">

    <!-- ── Cabeçalho ───────────────────────────────────────── -->
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Usuários Admin</h1>
        <p class="mt-1 text-sm text-stone-400">
          Gerencie quem acessa o painel e qual área cada usuário é responsável.
        </p>
      </div>

      <div class="flex w-full max-w-lg flex-col gap-3 lg:flex-row lg:items-center lg:justify-end">
        <input
          v-model="search"
          type="search"
          placeholder="Buscar por nome ou e-mail"
          class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition placeholder:text-stone-500 focus:border-amber-500/40"
          @input="page = 1"
        />
        <button
          type="button"
          class="shrink-0 rounded-2xl bg-amber-500 px-4 py-3 text-center text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
          @click="openCreate"
        >
          Novo usuário
        </button>
      </div>
    </div>

    <!-- ── Erro ───────────────────────────────────────────── -->
    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <!-- ── Cards de resumo ────────────────────────────────── -->
    <div class="grid gap-4 md:grid-cols-3">
      <div class="rounded-2xl border border-sky-500/20 bg-sky-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-sky-300">Total de admins</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary.total }}</p>
      </div>
      <div class="rounded-2xl border border-red-500/20 bg-red-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-red-300">Super Admin</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary.super_admins }}</p>
      </div>
      <div class="rounded-2xl border border-violet-500/20 bg-violet-500/5 p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-violet-300">Outros perfis</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ summary.others }}</p>
      </div>
    </div>

    <!-- ── Filtros de perfil ──────────────────────────────── -->
    <div class="flex flex-wrap items-center gap-2">
      <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Perfil</span>
      <button
        type="button"
        :class="[
          'flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs font-medium transition',
          roleFilter === ''
            ? 'border-amber-500/40 bg-amber-500/10 text-amber-300'
            : 'border-white/10 bg-white/5 text-stone-400 hover:border-white/20 hover:text-stone-200',
        ]"
        @click="roleFilter = ''; page = 1"
      >
        Todos
      </button>
      <button
        v-for="role in FIXED_ROLES"
        :key="role.value"
        type="button"
        :class="[
          'flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs font-medium transition',
          roleFilter === role.value
            ? 'border-amber-500/40 bg-amber-500/10 text-amber-300'
            : 'border-white/10 bg-white/5 text-stone-400 hover:border-white/20 hover:text-stone-200',
        ]"
        @click="roleFilter = role.value; page = 1"
      >
        {{ role.label }}
      </button>
    </div>

    <!-- ── Loading ────────────────────────────────────────── -->
    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando usuários…</div>

    <!-- ── Vazio ──────────────────────────────────────────── -->
    <div
      v-else-if="sortedUsers.length === 0"
      class="rounded-2xl border border-white/10 bg-white/5 px-6 py-12 text-center text-stone-500"
    >
      Nenhum usuário encontrado para os filtros atuais.
    </div>

    <!-- ── Tabela ──────────────────────────────────────────── -->
    <template v-else>
      <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/5">
        <table class="min-w-full divide-y divide-white/10 text-sm">
          <thead class="bg-black/20 text-left text-xs uppercase tracking-widest text-stone-500">
            <tr>
              <ThSort :state="sortState('name')" @click="toggleSort('name'); page = 1">Nome</ThSort>
              <ThSort :state="sortState('admin_role')" @click="toggleSort('admin_role'); page = 1">Perfil / Área</ThSort>
              <ThSort :state="sortState('created_at')" class="hidden sm:table-cell" @click="toggleSort('created_at'); page = 1">Criado em</ThSort>
              <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-widest">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5">
            <tr v-for="user in paginatedUsers" :key="user.id" class="hover:bg-white/5">
              <td class="px-4 py-4">
                <div class="font-medium text-white">{{ user.name }}</div>
                <div class="mt-1 text-xs text-stone-500">{{ user.email }}</div>
              </td>
              <td class="px-4 py-4">
                <span
                  class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold"
                  :class="displayRoleBadge(user)"
                >
                  {{ displayRoleLabel(user) }}
                </span>
                <span v-if="user.admin_role_id" class="ml-1.5 text-[10px] text-indigo-400">Personalizado</span>
              </td>
              <td class="hidden px-4 py-4 text-stone-400 sm:table-cell">
                {{ formatDate(user.created_at) }}
              </td>
              <td class="px-4 py-4">
                <div class="flex items-center justify-end gap-2">
                  <button
                    type="button"
                    class="rounded-xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-300 transition hover:bg-sky-500/20"
                    @click="openEdit(user)"
                  >
                    Editar
                  </button>
                  <button
                    v-if="user.id !== auth.user?.id"
                    type="button"
                    class="rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-300 transition hover:bg-rose-500/20"
                    @click="confirmRevoke(user)"
                  >
                    Revogar
                  </button>
                  <span v-else class="text-xs italic text-stone-500">você</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <AppPagination
        :meta="paginationMeta"
        @update:page="(p) => { page = p }"
        @update:perPage="(n) => { perPage = n; page = 1 }"
      />
    </template>

    <!-- ── Modal criar / editar ──────────────────────────── -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
      @click.self="showModal = false"
    >
      <div class="w-full max-w-md rounded-3xl border border-white/10 bg-stone-950 p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 class="text-xl font-semibold text-white">
              {{ modalMode === 'create' ? 'Novo usuário admin' : 'Editar usuário' }}
            </h2>
            <p class="mt-1 text-sm text-stone-400">
              {{ modalMode === 'create'
                  ? 'Crie uma conta com acesso ao painel administrativo.'
                  : 'Altere o nome, perfil ou senha do usuário.' }}
            </p>
          </div>
          <button type="button" class="text-stone-500 hover:text-stone-300" @click="showModal = false">✕</button>
        </div>

        <div
          v-if="formError"
          class="mt-4 rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300"
        >
          {{ formError }}
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="submitForm">
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Nome *</label>
            <input v-model="form.name" type="text" required class="input" placeholder="Nome completo" />
          </div>

          <div>
            <label class="mb-1.5 block text-sm text-stone-300">E-mail *</label>
            <input
              v-model="form.email"
              type="email"
              required
              :disabled="modalMode === 'edit'"
              class="input disabled:opacity-50"
              placeholder="email@admin.com"
            />
          </div>

          <div>
            <label class="mb-1.5 block text-sm text-stone-300">
              {{ modalMode === 'create' ? 'Senha *' : 'Nova senha (deixe vazio para manter)' }}
            </label>
            <input
              v-model="form.password"
              type="password"
              :required="modalMode === 'create'"
              autocomplete="new-password"
              class="input"
            />
          </div>

          <!-- Tipo de perfil -->
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Tipo de acesso *</label>
            <div class="flex gap-3">
              <label class="flex cursor-pointer items-center gap-2">
                <input v-model="form.roleType" type="radio" value="fixed" class="accent-amber-500" />
                <span class="text-sm text-stone-300">Perfil fixo</span>
              </label>
              <label class="flex cursor-pointer items-center gap-2">
                <input v-model="form.roleType" type="radio" value="custom" class="accent-amber-500" />
                <span class="text-sm text-stone-300">Perfil personalizado</span>
              </label>
            </div>
          </div>

          <!-- Perfil fixo -->
          <div v-if="form.roleType === 'fixed'">
            <label class="mb-1.5 block text-sm text-stone-300">Perfil / Área *</label>
            <select v-model="form.admin_role" class="input">
              <option v-for="role in FIXED_ROLES" :key="role.value" :value="role.value">
                {{ role.label }}
              </option>
            </select>
            <p class="mt-1.5 text-xs text-stone-500">{{ roleDesc(form.admin_role) }}</p>
          </div>

          <!-- Perfil personalizado -->
          <div v-else>
            <label class="mb-1.5 block text-sm text-stone-300">Perfil personalizado *</label>
            <select v-model="form.admin_role_id" class="input">
              <option :value="null">Selecione um perfil…</option>
              <option v-for="role in globalRoles" :key="role.id" :value="role.id">
                {{ role.name }} ({{ role.permissions.length }} permissão(ões))
              </option>
            </select>
            <p v-if="globalRoles.length === 0" class="mt-1.5 text-xs text-amber-400">
              Nenhum perfil personalizado criado ainda. Acesse <strong>Perfis</strong> para criar.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-3 pt-2">
            <button
              type="submit"
              :disabled="formLoading"
              class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
            >
              {{ formLoading ? 'Salvando…' : (modalMode === 'create' ? 'Criar usuário' : 'Salvar alterações') }}
            </button>
            <button
              type="button"
              class="rounded-xl border border-white/10 px-6 py-2.5 text-sm text-stone-400 transition hover:text-stone-200"
              @click="showModal = false"
            >
              Cancelar
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Modal confirmar revogação ─────────────────────── -->
    <div
      v-if="showRevokeConfirm"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
      @click.self="showRevokeConfirm = false"
    >
      <div class="w-full max-w-sm rounded-3xl border border-white/10 bg-stone-950 p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
          <h2 class="text-xl font-semibold text-white">Revogar acesso</h2>
          <button type="button" class="text-stone-500 hover:text-stone-300" @click="showRevokeConfirm = false">✕</button>
        </div>
        <p class="mt-3 text-sm text-stone-400">
          Tem certeza que deseja revogar o acesso administrativo de
          <strong class="text-white">{{ revokeTarget?.name }}</strong>?
          O usuário não poderá mais entrar no painel admin.
        </p>

        <div
          v-if="revokeError"
          class="mt-4 rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300"
        >
          {{ revokeError }}
        </div>

        <div class="mt-5 flex flex-wrap gap-3">
          <button
            type="button"
            :disabled="revokeLoading"
            class="rounded-xl bg-rose-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-500 disabled:opacity-50"
            @click="doRevoke"
          >
            {{ revokeLoading ? 'Revogando…' : 'Revogar acesso' }}
          </button>
          <button
            type="button"
            class="rounded-xl border border-white/10 px-6 py-2.5 text-sm text-stone-400 transition hover:text-stone-200"
            @click="showRevokeConfirm = false"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
