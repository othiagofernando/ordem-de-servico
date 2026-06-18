<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  ADMIN_ROLE_LABELS,
  AdminUser,
  createAdminUser,
  listAdminUsers,
  revokeAdminUser,
  updateAdminUser,
} from '../../api/admin'
import { ApiError } from '../../api/client'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const users = ref<AdminUser[]>([])
const loading = ref(true)
const error = ref('')

const search = ref('')
const filteredUsers = computed(() => {
  const q = search.value.toLowerCase().trim()
  if (!q) return users.value
  return users.value.filter(
    (u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q),
  )
})

const showModal = ref(false)
const modalMode = ref<'create' | 'edit'>('create')
const selectedUser = ref<AdminUser | null>(null)

const form = ref({
  name: '',
  email: '',
  password: '',
  admin_role: 'support',
})
const formLoading = ref(false)
const formError = ref('')

const showRevokeConfirm = ref(false)
const revokeTarget = ref<AdminUser | null>(null)
const revokeLoading = ref(false)
const revokeError = ref('')

const ROLES = Object.entries(ADMIN_ROLE_LABELS).map(([value, label]) => ({ value, label }))

const ROLE_COLORS: Record<string, string> = {
  super_admin: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
  support: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
  billing: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
  plans: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
  viewer: 'bg-gray-100 text-gray-700 dark:bg-gray-700/50 dark:text-gray-300',
}

function roleColor(role: string) {
  return ROLE_COLORS[role] ?? 'bg-gray-100 text-gray-700'
}

function roleLabel(role: string) {
  return ADMIN_ROLE_LABELS[role] ?? role
}

function openCreate() {
  modalMode.value = 'create'
  selectedUser.value = null
  form.value = { name: '', email: '', password: '', admin_role: 'support' }
  formError.value = ''
  showModal.value = true
}

function openEdit(user: AdminUser) {
  modalMode.value = 'edit'
  selectedUser.value = user
  form.value = { name: user.name, email: user.email, password: '', admin_role: user.admin_role }
  formError.value = ''
  showModal.value = true
}

async function submitForm() {
  formError.value = ''
  formLoading.value = true
  try {
    if (modalMode.value === 'create') {
      const { data } = await createAdminUser({
        name: form.value.name,
        email: form.value.email,
        password: form.value.password,
        admin_role: form.value.admin_role,
      })
      users.value.push(data)
      users.value.sort((a, b) => a.name.localeCompare(b.name))
    } else if (selectedUser.value) {
      const payload: { name?: string; admin_role?: string; password?: string } = {
        name: form.value.name,
        admin_role: form.value.admin_role,
      }
      if (form.value.password) payload.password = form.value.password
      const { data } = await updateAdminUser(selectedUser.value.id, payload)
      const idx = users.value.findIndex((u) => u.id === data.id)
      if (idx !== -1) users.value[idx] = data
      users.value.sort((a, b) => a.name.localeCompare(b.name))
    }
    showModal.value = false
  } catch (e) {
    if (e instanceof ApiError) formError.value = e.message
    else formError.value = 'Erro inesperado. Tente novamente.'
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
    if (e instanceof ApiError) revokeError.value = e.message
    else revokeError.value = 'Erro inesperado. Tente novamente.'
  } finally {
    revokeLoading.value = false
  }
}

function formatDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('pt-BR')
}

onMounted(async () => {
  try {
    const { data } = await listAdminUsers()
    users.value = data
  } catch {
    error.value = 'Erro ao carregar usuários administrativos.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Usuários Admin</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Gerencie quem tem acesso ao painel administrativo e qual área cada um gerencia.
        </p>
      </div>
      <button class="btn btn-primary" @click="openCreate">+ Novo usuário</button>
    </div>

    <div v-if="error" class="rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
      {{ error }}
    </div>

    <div class="card">
      <div class="mb-4">
        <input
          v-model="search"
          type="search"
          placeholder="Buscar por nome ou e-mail..."
          class="input max-w-xs"
        />
      </div>

      <div v-if="loading" class="py-12 text-center text-gray-400">Carregando...</div>

      <div v-else-if="filteredUsers.length === 0" class="py-12 text-center text-gray-400">
        Nenhum usuário encontrado.
      </div>

      <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead>
          <tr>
            <th class="th">Usuário</th>
            <th class="th">Perfil</th>
            <th class="th hidden sm:table-cell">Criado em</th>
            <th class="th text-right">Ações</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <tr v-for="user in filteredUsers" :key="user.id" class="group hover:bg-gray-50 dark:hover:bg-gray-800/30">
            <td class="td">
              <div class="font-medium text-gray-900 dark:text-white">{{ user.name }}</div>
              <div class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</div>
            </td>
            <td class="td">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="roleColor(user.admin_role)"
              >
                {{ roleLabel(user.admin_role) }}
              </span>
            </td>
            <td class="td hidden sm:table-cell text-gray-500 dark:text-gray-400 text-sm">
              {{ formatDate(user.created_at) }}
            </td>
            <td class="td text-right">
              <div class="flex items-center justify-end gap-2">
                <button
                  class="btn btn-sm btn-secondary"
                  @click="openEdit(user)"
                >
                  Editar
                </button>
                <button
                  v-if="user.id !== auth.user?.id"
                  class="btn btn-sm btn-danger-outline"
                  @click="confirmRevoke(user)"
                >
                  Revogar
                </button>
                <span
                  v-else
                  class="text-xs text-gray-400 dark:text-gray-500 italic"
                >
                  você
                </span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal criar/editar -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div class="card w-full max-w-md">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          {{ modalMode === 'create' ? 'Novo usuário admin' : 'Editar usuário' }}
        </h2>

        <div v-if="formError" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
          {{ formError }}
        </div>

        <form class="space-y-4" @submit.prevent="submitForm">
          <div>
            <label class="label">Nome</label>
            <input v-model="form.name" type="text" class="input" required />
          </div>

          <div>
            <label class="label">E-mail</label>
            <input
              v-model="form.email"
              type="email"
              class="input"
              required
              :disabled="modalMode === 'edit'"
            />
          </div>

          <div>
            <label class="label">
              {{ modalMode === 'create' ? 'Senha' : 'Nova senha (deixe em branco para manter)' }}
            </label>
            <input
              v-model="form.password"
              type="password"
              class="input"
              :required="modalMode === 'create'"
              autocomplete="new-password"
            />
          </div>

          <div>
            <label class="label">Perfil / Área</label>
            <select v-model="form.admin_role" class="input">
              <option v-for="role in ROLES" :key="role.value" :value="role.value">
                {{ role.label }}
              </option>
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              <span v-if="form.admin_role === 'super_admin'">Acesso total ao painel. Pode gerenciar outros admins.</span>
              <span v-else-if="form.admin_role === 'support'">Acesso a empresas, auditoria e impersonação.</span>
              <span v-else-if="form.admin_role === 'billing'">Acesso a cobranças e assinaturas.</span>
              <span v-else-if="form.admin_role === 'plans'">Acesso a planos e preços.</span>
              <span v-else-if="form.admin_role === 'viewer'">Leitura de todos os dados (sem edição).</span>
            </p>
          </div>

          <div class="flex gap-3 pt-2">
            <button type="submit" class="btn btn-primary flex-1" :disabled="formLoading">
              {{ formLoading ? 'Salvando...' : (modalMode === 'create' ? 'Criar usuário' : 'Salvar alterações') }}
            </button>
            <button type="button" class="btn btn-secondary" @click="showModal = false">
              Cancelar
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal confirmação de revogação -->
    <div v-if="showRevokeConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div class="card w-full max-w-sm">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Revogar acesso</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          Tem certeza que deseja revogar o acesso administrativo de
          <strong>{{ revokeTarget?.name }}</strong>? O usuário não poderá mais acessar o painel admin.
        </p>

        <div v-if="revokeError" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
          {{ revokeError }}
        </div>

        <div class="flex gap-3">
          <button
            class="btn btn-danger flex-1"
            :disabled="revokeLoading"
            @click="doRevoke"
          >
            {{ revokeLoading ? 'Revogando...' : 'Revogar acesso' }}
          </button>
          <button class="btn btn-secondary" @click="showRevokeConfirm = false">Cancelar</button>
        </div>
      </div>
    </div>
  </div>
</template>
