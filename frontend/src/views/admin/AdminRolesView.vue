<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  type AdminGlobalRole,
  listAdminRoles,
  createAdminRole,
  updateAdminRole,
  deleteAdminRole,
} from '../../api/admin'
import { ApiError } from '../../api/client'
import { useSortable } from '../../composables/useSortable'
import ThSort from '../../components/ThSort.vue'
import AppPagination from '../../components/AppPagination.vue'

const roles = ref<AdminGlobalRole[]>([])
const loading = ref(true)
const error = ref('')

const page = ref(1)
const perPage = ref(15)

const ADMIN_PERMISSIONS = [
  { value: 'overview',         label: 'Resumo',                  desc: 'Página inicial do painel admin' },
  { value: 'alerts',           label: 'Alertas',                  desc: 'Faturas vencidas, assinaturas expirando' },
  { value: 'companies',        label: 'Empresas (leitura)',        desc: 'Visualizar empresas e assinaturas' },
  { value: 'companies.manage', label: 'Empresas (gestão)',         desc: 'Criar, editar, suspender e impersonar' },
  { value: 'subscriptions',    label: 'Assinaturas',              desc: 'Visualizar e gerenciar assinaturas' },
  { value: 'billing',          label: 'Cobranças / Faturas',      desc: 'Visualizar faturas e cobranças' },
  { value: 'plans',            label: 'Planos',                   desc: 'Criar e editar planos' },
  { value: 'audit',            label: 'Auditoria',                desc: 'Visualizar logs de auditoria' },
]

// ── ordenação ─────────────────────────────────────────────
const { sorted: sortedRoles, toggleSort, sortState } = useSortable(roles, {
  name:        (r) => r.name,
  permissions: (r) => r.permissions.length,
  users_count: (r) => r.users_count ?? 0,
  created_at:  (r) => r.created_at,
})

// ── paginação ─────────────────────────────────────────────
const paginationMeta = computed(() => {
  const total = sortedRoles.value.length
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

const paginatedRoles = computed(() => {
  const start = (page.value - 1) * perPage.value
  return sortedRoles.value.slice(start, start + perPage.value)
})

// ── modal criar/editar ───────────────────────────────────
const showModal = ref(false)
const modalMode = ref<'create' | 'edit'>('create')
const selectedRole = ref<AdminGlobalRole | null>(null)
const form = ref({ name: '', permissions: [] as string[] })
const formLoading = ref(false)
const formError = ref('')

// ── modal excluir ────────────────────────────────────────
const showDeleteConfirm = ref(false)
const deleteTarget = ref<AdminGlobalRole | null>(null)
const deleteLoading = ref(false)
const deleteError = ref('')

function openCreate() {
  modalMode.value = 'create'
  selectedRole.value = null
  form.value = { name: '', permissions: [] }
  formError.value = ''
  showModal.value = true
}

function openEdit(role: AdminGlobalRole) {
  modalMode.value = 'edit'
  selectedRole.value = role
  form.value = { name: role.name, permissions: [...role.permissions] }
  formError.value = ''
  showModal.value = true
}

function togglePermission(perm: string) {
  const idx = form.value.permissions.indexOf(perm)
  if (idx === -1) {
    form.value.permissions.push(perm)
  } else {
    form.value.permissions.splice(idx, 1)
  }
}

async function submitForm() {
  formError.value = ''
  formLoading.value = true
  try {
    if (modalMode.value === 'create') {
      const { data } = await createAdminRole({
        name: form.value.name,
        permissions: form.value.permissions,
      })
      roles.value.push(data)
    } else if (selectedRole.value) {
      const { data } = await updateAdminRole(selectedRole.value.id, {
        name: form.value.name,
        permissions: form.value.permissions,
      })
      const idx = roles.value.findIndex((r) => r.id === data.id)
      if (idx !== -1) roles.value[idx] = data
    }
    showModal.value = false
  } catch (e) {
    formError.value = e instanceof ApiError ? e.message : 'Erro inesperado. Tente novamente.'
  } finally {
    formLoading.value = false
  }
}

function confirmDelete(role: AdminGlobalRole) {
  deleteTarget.value = role
  deleteError.value = ''
  showDeleteConfirm.value = true
}

async function doDelete() {
  if (!deleteTarget.value) return
  deleteLoading.value = true
  deleteError.value = ''
  try {
    await deleteAdminRole(deleteTarget.value.id)
    roles.value = roles.value.filter((r) => r.id !== deleteTarget.value!.id)
    showDeleteConfirm.value = false
  } catch (e) {
    deleteError.value = e instanceof ApiError ? e.message : 'Erro inesperado. Tente novamente.'
  } finally {
    deleteLoading.value = false
  }
}

function formatDate(iso: string | null) {
  if (!iso) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(new Date(iso))
}

function permLabel(perm: string): string {
  return ADMIN_PERMISSIONS.find((p) => p.value === perm)?.label ?? perm
}

onMounted(async () => {
  try {
    const { data } = await listAdminRoles()
    roles.value = data
  } catch {
    error.value = 'Erro ao carregar perfis de acesso.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">

    <!-- ── Cabeçalho ──────────────────────────────────────── -->
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Perfis de Acesso</h1>
        <p class="mt-1 text-sm text-stone-400">
          Crie perfis personalizados com permissões específicas e atribua-os a usuários admin.
        </p>
      </div>
      <button
        type="button"
        class="shrink-0 rounded-2xl bg-amber-500 px-4 py-3 text-center text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
        @click="openCreate"
      >
        Novo perfil
      </button>
    </div>

    <!-- ── Erro ───────────────────────────────────────────── -->
    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <!-- ── Loading ────────────────────────────────────────── -->
    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando perfis…</div>

    <!-- ── Vazio ──────────────────────────────────────────── -->
    <div
      v-else-if="sortedRoles.length === 0"
      class="rounded-2xl border border-white/10 bg-white/5 px-6 py-12 text-center text-stone-500"
    >
      Nenhum perfil personalizado criado ainda. Clique em <strong class="text-stone-300">Novo perfil</strong> para começar.
    </div>

    <!-- ── Tabela ─────────────────────────────────────────── -->
    <template v-else>
      <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/5">
        <table class="min-w-full divide-y divide-white/10 text-sm">
          <thead class="bg-black/20 text-left text-xs uppercase tracking-widest text-stone-500">
            <tr>
              <ThSort :state="sortState('name')" @click="toggleSort('name'); page = 1">Nome</ThSort>
              <ThSort :state="sortState('permissions')" @click="toggleSort('permissions'); page = 1">Permissões</ThSort>
              <ThSort :state="sortState('users_count')" @click="toggleSort('users_count'); page = 1">Usuários</ThSort>
              <ThSort :state="sortState('created_at')" class="hidden sm:table-cell" @click="toggleSort('created_at'); page = 1">Criado em</ThSort>
              <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-widest">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5">
            <tr v-for="role in paginatedRoles" :key="role.id" class="hover:bg-white/5">
              <td class="px-4 py-4">
                <div class="font-medium text-white">{{ role.name }}</div>
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="perm in role.permissions"
                    :key="perm"
                    class="inline-flex items-center rounded-full border border-indigo-500/30 bg-indigo-500/10 px-2 py-0.5 text-[11px] font-medium text-indigo-300"
                  >
                    {{ permLabel(perm) }}
                  </span>
                  <span v-if="role.permissions.length === 0" class="text-xs text-stone-500">—</span>
                </div>
              </td>
              <td class="px-4 py-4 text-stone-400">
                {{ role.users_count ?? 0 }}
              </td>
              <td class="hidden px-4 py-4 text-stone-400 sm:table-cell">
                {{ formatDate(role.created_at) }}
              </td>
              <td class="px-4 py-4">
                <div class="flex items-center justify-end gap-2">
                  <button
                    type="button"
                    class="rounded-xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-300 transition hover:bg-sky-500/20"
                    @click="openEdit(role)"
                  >
                    Editar
                  </button>
                  <button
                    type="button"
                    class="rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-300 transition hover:bg-rose-500/20"
                    @click="confirmDelete(role)"
                  >
                    Excluir
                  </button>
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
      <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-stone-950 p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 class="text-xl font-semibold text-white">
              {{ modalMode === 'create' ? 'Novo perfil de acesso' : 'Editar perfil' }}
            </h2>
            <p class="mt-1 text-sm text-stone-400">
              {{ modalMode === 'create'
                  ? 'Defina um nome e selecione as seções que este perfil pode acessar.'
                  : 'Altere o nome ou as permissões deste perfil.' }}
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

        <form class="mt-5 space-y-5" @submit.prevent="submitForm">
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Nome do perfil *</label>
            <input v-model="form.name" type="text" required class="input" placeholder="Ex: Suporte Nível 1" />
          </div>

          <div>
            <label class="mb-2 block text-sm text-stone-300">Permissões de acesso</label>
            <div class="space-y-2 rounded-2xl border border-white/10 bg-white/5 p-4">
              <label
                v-for="perm in ADMIN_PERMISSIONS"
                :key="perm.value"
                class="flex cursor-pointer items-start gap-3 rounded-xl p-2 transition hover:bg-white/5"
              >
                <input
                  type="checkbox"
                  :checked="form.permissions.includes(perm.value)"
                  class="mt-0.5 h-4 w-4 shrink-0 accent-indigo-500"
                  @change="togglePermission(perm.value)"
                />
                <div>
                  <p class="text-sm font-medium text-white">{{ perm.label }}</p>
                  <p class="text-xs text-stone-500">{{ perm.desc }}</p>
                </div>
              </label>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-3 pt-1">
            <button
              type="submit"
              :disabled="formLoading"
              class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
            >
              {{ formLoading ? 'Salvando…' : (modalMode === 'create' ? 'Criar perfil' : 'Salvar alterações') }}
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

    <!-- ── Modal confirmar exclusão ───────────────────────── -->
    <div
      v-if="showDeleteConfirm"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
      @click.self="showDeleteConfirm = false"
    >
      <div class="w-full max-w-sm rounded-3xl border border-white/10 bg-stone-950 p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
          <h2 class="text-xl font-semibold text-white">Excluir perfil</h2>
          <button type="button" class="text-stone-500 hover:text-stone-300" @click="showDeleteConfirm = false">✕</button>
        </div>
        <p class="mt-3 text-sm text-stone-400">
          Tem certeza que deseja excluir o perfil
          <strong class="text-white">{{ deleteTarget?.name }}</strong>?
          Esta ação não pode ser desfeita.
        </p>

        <div
          v-if="deleteError"
          class="mt-4 rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300"
        >
          {{ deleteError }}
        </div>

        <div class="mt-5 flex flex-wrap gap-3">
          <button
            type="button"
            :disabled="deleteLoading"
            class="rounded-xl bg-rose-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-500 disabled:opacity-50"
            @click="doDelete"
          >
            {{ deleteLoading ? 'Excluindo…' : 'Excluir perfil' }}
          </button>
          <button
            type="button"
            class="rounded-xl border border-white/10 px-6 py-2.5 text-sm text-stone-400 transition hover:text-stone-200"
            @click="showDeleteConfirm = false"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
