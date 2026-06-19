<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  type CompanyRole,
  createCompanyRole,
  deleteCompanyRole,
  listCompanyRoles,
  updateCompanyRole,
} from '../../api/memberships'
import { ApiError } from '../../api/client'
import { useSortable } from '../../composables/useSortable'
import ThSort from '../../components/ThSort.vue'
import AppPagination from '../../components/AppPagination.vue'

// ── Estado ────────────────────────────────────────────────────────────────────
const loading = ref(true)
const roles = ref<CompanyRole[]>([])
const error = ref('')

// ── Ordenação + paginação ──────────────────────────────────────────────────
const { sorted, toggleSort, sortState } = useSortable(roles, {
  name: (r: CompanyRole) => r.name,
  permissions: (r: CompanyRole) => r.permissions.length,
})

const page = ref(1)
const perPage = ref(20)

const paginationMeta = computed(() => {
  const total = sorted.value.length
  const lastPage = Math.ceil(total / perPage.value) || 1
  const from = (page.value - 1) * perPage.value + 1
  const to = Math.min(page.value * perPage.value, total)
  return { total, per_page: perPage.value, current_page: page.value, last_page: lastPage, from: total === 0 ? 0 : from, to }
})

const paginated = computed(() => {
  const start = (page.value - 1) * perPage.value
  return sorted.value.slice(start, start + perPage.value)
})

function resetPage() { page.value = 1 }

// ── Modal criar/editar ─────────────────────────────────────────────────────
const formModal = ref<{ role: CompanyRole | null } | null>(null)
const formName = ref('')
const formPerms = ref<string[]>([])
const formLoading = ref(false)
const formError = ref('')

function openCreate() {
  formName.value = ''
  formPerms.value = []
  formError.value = ''
  formModal.value = { role: null }
}

function openEdit(role: CompanyRole) {
  formName.value = role.name
  formPerms.value = [...role.permissions]
  formError.value = ''
  formModal.value = { role }
}

function closeForm() {
  formModal.value = null
}

function togglePerm(perm: string) {
  const idx = formPerms.value.indexOf(perm)
  if (idx === -1) formPerms.value.push(perm)
  else formPerms.value.splice(idx, 1)
}

async function submitForm() {
  if (!formName.value.trim()) { formError.value = 'O nome é obrigatório.'; return }
  formLoading.value = true
  formError.value = ''
  try {
    if (formModal.value?.role) {
      await updateCompanyRole(formModal.value.role.id, { name: formName.value.trim(), permissions: formPerms.value })
    } else {
      await createCompanyRole({ name: formName.value.trim(), permissions: formPerms.value })
    }
    closeForm()
    await load()
  } catch (e) {
    if (e instanceof ApiError) {
      const d = e.data as { message?: string; errors?: Record<string, string[]> }
      formError.value = (d.errors ? Object.values(d.errors)[0]?.[0] : null) ?? d.message ?? 'Erro ao salvar.'
    } else {
      formError.value = 'Erro inesperado.'
    }
  } finally {
    formLoading.value = false
  }
}

// ── Modal excluir ──────────────────────────────────────────────────────────
const deleteModal = ref<CompanyRole | null>(null)
const deleteLoading = ref(false)
const deleteError = ref('')

function openDelete(role: CompanyRole) {
  deleteModal.value = role
  deleteError.value = ''
}

async function confirmDelete() {
  if (!deleteModal.value) return
  deleteLoading.value = true
  deleteError.value = ''
  try {
    await deleteCompanyRole(deleteModal.value.id)
    deleteModal.value = null
    await load()
  } catch (e) {
    deleteError.value = (e instanceof ApiError ? (e.data as { message?: string })?.message : null) ?? 'Erro ao remover.'
  } finally {
    deleteLoading.value = false
  }
}

// ── Permissões disponíveis ─────────────────────────────────────────────────
const permissionGroups = [
  { label: 'Equipe', prefix: 'memberships' },
  { label: 'Configurações', prefix: 'company_settings' },
  { label: 'Clientes', prefix: 'customers' },
  { label: 'Equipamentos', prefix: 'equipments' },
  { label: 'Ordens de Serviço', prefix: 'service_orders' },
  { label: 'Peças / Estoque', prefix: 'parts' },
  { label: 'Avaliações', prefix: 'ratings' },
]

const allPermissions = [
  'memberships.manage',
  'company_settings.manage',
  'customers.view_any',
  'customers.manage',
  'equipments.view_any',
  'equipments.manage',
  'equipments.override_serial_uniqueness',
  'service_orders.view_any',
  'service_orders.view_assigned',
  'service_orders.manage',
  'service_orders.assign',
  'service_orders.diagnose',
  'service_orders.execute',
  'service_orders.finance',
  'service_orders.override_delivery_balance',
  'parts.view_any',
  'parts.manage',
  'ratings.view_any',
]

const permissionLabels: Record<string, string> = {
  'memberships.manage': 'Gerenciar equipe',
  'company_settings.manage': 'Gerenciar configurações',
  'customers.view_any': 'Visualizar clientes',
  'customers.manage': 'Gerenciar clientes',
  'equipments.view_any': 'Visualizar equipamentos',
  'equipments.manage': 'Gerenciar equipamentos',
  'equipments.override_serial_uniqueness': 'Ignorar unicidade de serial',
  'service_orders.view_any': 'Visualizar todas as O.S.',
  'service_orders.view_assigned': 'Visualizar O.S. atribuídas',
  'service_orders.manage': 'Gerenciar O.S.',
  'service_orders.assign': 'Atribuir técnico',
  'service_orders.diagnose': 'Diagnosticar',
  'service_orders.execute': 'Executar',
  'service_orders.finance': 'Financeiro',
  'service_orders.override_delivery_balance': 'Liberar entrega com saldo',
  'parts.view_any': 'Visualizar peças e estoque',
  'parts.manage': 'Gerenciar peças e estoque',
  'ratings.view_any': 'Visualizar avaliações',
}

function permsByGroup(prefix: string) {
  return allPermissions.filter((p) => p.startsWith(prefix + '.'))
}

// ── Dados ─────────────────────────────────────────────────────────────────
onMounted(load)

async function load() {
  loading.value = true
  error.value = ''
  try {
    roles.value = (await listCompanyRoles()).data
  } catch {
    error.value = 'Não foi possível carregar os perfis.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold" style="color: var(--c-text)">Perfis Customizados</h1>
        <p class="mt-1 text-sm" style="color: var(--c-text-muted)">
          Crie perfis com conjuntos específicos de permissões para atribuir aos membros da equipe.
        </p>
      </div>
      <button class="btn-primary shrink-0" @click="openCreate">+ Novo Perfil</button>
    </div>

    <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ error }}</div>

    <!-- Tabela -->
    <div class="card overflow-hidden p-0">
      <table class="w-full min-w-[460px]">
        <thead>
          <tr style="background: var(--c-table-head); border-bottom: 1px solid var(--c-border)">
            <ThSort :state="sortState('name')" @click="toggleSort('name'); resetPage()">Nome</ThSort>
            <ThSort :state="sortState('permissions')" @click="toggleSort('permissions'); resetPage()">Permissões</ThSort>
            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-widest" style="color: var(--c-text-muted)">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="3" class="px-5 py-12 text-center text-sm" style="color: var(--c-text-dim)">Carregando…</td>
          </tr>
          <tr v-else-if="sorted.length === 0">
            <td colspan="3" class="px-5 py-12 text-center text-sm" style="color: var(--c-text-muted)">
              Nenhum perfil customizado criado ainda. Crie o primeiro clicando em "Novo Perfil".
            </td>
          </tr>
          <template v-else>
            <tr
              v-for="role in paginated"
              :key="role.id"
              class="t-row"
              style="border-top: 1px solid var(--c-border)"
            >
              <td class="px-5 py-4">
                <p class="text-sm font-semibold" style="color: var(--c-text)">{{ role.name }}</p>
              </td>
              <td class="px-5 py-4">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-if="role.permissions.length === 0"
                    class="text-xs"
                    style="color: var(--c-text-dim)"
                  >Nenhuma permissão</span>
                  <span
                    v-for="perm in role.permissions"
                    :key="perm"
                    class="perm-chip"
                    :title="perm"
                  >{{ permissionLabels[perm] ?? perm }}</span>
                </div>
              </td>
              <td class="px-5 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button class="btn-ghost text-xs" @click="openEdit(role)">Editar</button>
                  <button class="btn-ghost btn-ghost-danger text-xs" @click="openDelete(role)">Excluir</button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <AppPagination
      :meta="paginationMeta"
      @update:page="(p) => { page = p }"
      @update:perPage="(n) => { perPage = n; page = 1 }"
    />

    <!-- Modal criar/editar ─────────────────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="formModal !== null"
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/60 px-4 py-10"
        @click.self="closeForm"
      >
        <div class="modal-box w-full max-w-lg">
          <h2 class="text-base font-semibold" style="color: var(--c-text)">
            {{ formModal.role ? 'Editar perfil' : 'Novo perfil' }}
          </h2>

          <div class="mt-4 space-y-4">
            <!-- Nome -->
            <label class="block space-y-1.5">
              <span class="field-label">Nome do perfil</span>
              <input
                v-model="formName"
                type="text"
                placeholder="Ex: Consultor externo"
                class="input"
                @keydown.enter.prevent="submitForm"
              />
            </label>

            <!-- Permissões -->
            <div class="space-y-3">
              <p class="field-label">Permissões do perfil</p>
              <div
                v-for="group in permissionGroups"
                :key="group.prefix"
                class="perm-group"
              >
                <p class="perm-group-title">{{ group.label }}</p>
                <div class="grid gap-1 sm:grid-cols-2">
                  <label
                    v-for="perm in permsByGroup(group.prefix)"
                    :key="perm"
                    class="perm-row"
                  >
                    <input
                      type="checkbox"
                      :checked="formPerms.includes(perm)"
                      class="perm-check"
                      @change="togglePerm(perm)"
                    />
                    <span class="text-xs">{{ permissionLabels[perm] ?? perm }}</span>
                  </label>
                </div>
              </div>
            </div>

            <p v-if="formError" class="text-xs text-red-400">{{ formError }}</p>

            <div class="flex justify-end gap-2 border-t pt-4" style="border-color: var(--c-border)">
              <button class="btn-ghost text-sm" @click="closeForm">Cancelar</button>
              <button :disabled="formLoading" class="btn-primary text-sm" @click="submitForm">
                {{ formLoading ? 'Salvando…' : (formModal.role ? 'Salvar alterações' : 'Criar perfil') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal excluir ──────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="deleteModal !== null"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
        @click.self="deleteModal = null"
      >
        <div class="modal-box w-full max-w-sm">
          <h2 class="text-base font-semibold" style="color: var(--c-text)">Excluir perfil</h2>
          <p class="mt-2 text-sm" style="color: var(--c-text-muted)">
            O perfil <strong style="color: var(--c-text)">{{ deleteModal.name }}</strong> será removido.
            Membros que o utilizam perderão as permissões deste perfil.
          </p>
          <p v-if="deleteError" class="mt-2 text-xs text-red-400">{{ deleteError }}</p>
          <div class="mt-4 flex justify-end gap-2">
            <button class="btn-ghost text-sm" @click="deleteModal = null">Cancelar</button>
            <button :disabled="deleteLoading" class="btn-danger text-sm" @click="confirmDelete">
              {{ deleteLoading ? 'Excluindo…' : 'Excluir perfil' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@reference "../../style.css";

/* Layout card */
.card {
  @apply rounded-2xl border;
  background: var(--c-surface);
  border-color: var(--c-border);
}

/* Modal */
.modal-box {
  @apply rounded-2xl border p-6 shadow-2xl;
  background: var(--c-surface);
  border-color: var(--c-border-strong);
}

/* Titles */
.field-label {
  @apply block text-xs font-medium uppercase tracking-wider;
  color: var(--c-text-muted);
}

/* Input */
.input {
  @apply w-full rounded-xl border px-3 py-2 text-sm outline-none transition;
  color: var(--c-text);
  background: var(--c-input-bg);
  border-color: var(--c-input-border);
}
.input:focus { border-color: rgba(251,146,60,.5); box-shadow: 0 0 0 2px rgba(251,146,60,.08); }
.input::placeholder { color: var(--c-text-dim); }

/* Permission chip (read-only display in table) */
.perm-chip {
  @apply rounded px-1.5 py-0.5 text-[10px] font-medium;
  background: rgba(99,102,241,.1);
  color: #a5b4fc;
  border: 1px solid rgba(99,102,241,.2);
}

/* Permission editor in modal */
.perm-group {
  @apply rounded-xl border p-3 space-y-2;
  border-color: var(--c-border);
  background: var(--c-card);
}
.perm-group-title {
  @apply text-xs font-semibold uppercase tracking-wider;
  color: var(--c-text-muted);
}
.perm-row {
  @apply flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 transition;
  color: var(--c-text-muted);
}
.perm-row:hover { background: rgba(255,255,255,.04); }
.perm-check { @apply h-3.5 w-3.5 rounded accent-amber-500; }

/* Hover row */
.t-row { transition: background 0.1s; }
.t-row:hover { background: var(--c-nav-hover); }

/* Buttons */
.btn-primary {
  @apply rounded-xl px-4 py-2 text-sm font-semibold transition disabled:opacity-50;
  background: #f59e0b; color: #1c1917;
}
.btn-primary:hover:not(:disabled) { background: #fbbf24; }

.btn-ghost {
  @apply rounded-lg border px-3 py-1.5 text-sm font-medium transition;
  border-color: var(--c-border);
  color: var(--c-text-muted);
}
.btn-ghost:hover { border-color: var(--c-border-strong); color: var(--c-text); }

.btn-ghost-danger:hover {
  border-color: rgba(239,68,68,.35);
  color: #ef4444;
  background: rgba(239,68,68,.04);
}

.btn-danger {
  @apply rounded-xl px-4 py-2 text-sm font-semibold transition disabled:opacity-50;
  background: rgba(239,68,68,.15);
  color: #ef4444;
  border: 1px solid rgba(239,68,68,.3);
}
.btn-danger:hover:not(:disabled) { background: rgba(239,68,68,.25); }
</style>
