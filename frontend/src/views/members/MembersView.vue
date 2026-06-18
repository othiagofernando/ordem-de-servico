<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  inviteMembership,
  listMemberships,
  listRoles,
  removeMembership,
  type Membership,
  type RolePreset,
  updateMembershipAuthorization,
  updateMembershipStatus,
} from '../../api/memberships'
import { ApiError } from '../../api/client'
import { phoneMask } from '../../utils/masks'
import PasswordConfirmModal from '../../components/PasswordConfirmModal.vue'
import EditMemberModal from '../../components/EditMemberModal.vue'
import { useSortable } from '../../composables/useSortable'

// ── Estado global ─────────────────────────────────────────────────────────────
const loading = ref(true)
const members = ref<Membership[]>([])
const roles = ref<RolePreset[]>([])
const error = ref('')

// ── Filtros ───────────────────────────────────────────────────────────────────
const searchQuery = ref('')
const statusFilter = ref<string>('')
const roleFilter = ref<string>('')

// ── Modais ────────────────────────────────────────────────────────────────────
const editModal = ref<Membership | null>(null)
const removeModal = ref<{ member: Membership } | null>(null)
const removeLoading = ref(false)
const removeError = ref('')

// ── Convite ───────────────────────────────────────────────────────────────────
const inviteForm = ref({ name: '', email: '', role: 'technician' })
const inviteLoading = ref(false)
const inviteError = ref('')
const inviteSuccess = ref('')
const generatedInviteUrl = ref('')

// ── Status de ação ────────────────────────────────────────────────────────────
const statusLoadingId = ref<number | null>(null)

// ── Editor de perfil ──────────────────────────────────────────────────────────
const editingId = ref<number | null>(null)
const editRole = ref('')
const editCustomPerms = ref<string[]>([])
const editLoading = ref(false)
const editError = ref('')

// ── Ordenação ─────────────────────────────────────────────────────────────────
const { sorted: sortedMembers, toggleSort, sortState } = useSortable(members, {
  user_name: (m: Membership) => m.user_name ?? '',
  role: (m: Membership) => m.role,
  status: (m: Membership) => m.status,
})

// ── Labels e estilos ──────────────────────────────────────────────────────────
const roleLabels: Record<string, string> = {
  administrator: 'Administrador',
  manager: 'Gerente',
  attendant: 'Atendente',
  technician: 'Técnico',
  finance: 'Financeiro',
}

const roleDescriptions: Record<string, string> = {
  administrator: 'Administra membros, configurações e toda a operação.',
  manager: 'Coordena a operação completa, sem gestão de membros.',
  attendant: 'Cuida de cadastro, abertura e acompanhamento comercial da O.S.',
  technician: 'Atua somente nas O.S. atribuídas, com diagnóstico e execução.',
  finance: 'Acompanha recebimentos e liberações financeiras.',
}

const statusLabels: Record<string, string> = {
  active: 'Ativo',
  invited: 'Convidado',
  suspended: 'Suspenso',
}

const statusClasses: Record<string, string> = {
  active: 'badge-active',
  invited: 'badge-invited',
  suspended: 'badge-suspended',
}

// Grupos de permissões para exibição
const permissionGroups: { label: string; prefix: string }[] = [
  { label: 'Equipe', prefix: 'memberships' },
  { label: 'Configurações', prefix: 'company_settings' },
  { label: 'Clientes', prefix: 'customers' },
  { label: 'Equipamentos', prefix: 'equipments' },
  { label: 'Ordens de serviço', prefix: 'service_orders' },
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
}

// Todas as permissões disponíveis (extraídas dos presets carregados)
const allPermissions = computed<string[]>(() => {
  const set = new Set<string>()
  for (const r of roles.value) for (const p of r.permissions) set.add(p)
  return [...set]
})

// Permissões do preset do role em edição
const editRolePresetPerms = computed<string[]>(() => {
  return roles.value.find((r) => r.role === editRole.value)?.permissions ?? []
})

// ── Lista filtrada ─────────────────────────────────────────────────────────────
const filteredMembers = computed(() => {
  const q = searchQuery.value.toLowerCase().trim()
  return sortedMembers.value.filter((m) => {
    if (statusFilter.value && m.status !== statusFilter.value) return false
    if (roleFilter.value && m.role !== roleFilter.value) return false
    if (q) {
      const name = (m.user_name ?? '').toLowerCase()
      const email = (m.user_email ?? '').toLowerCase()
      const phone = (m.user_phone ?? '').toLowerCase()
      if (!name.includes(q) && !email.includes(q) && !phone.includes(q)) return false
    }
    return true
  })
})

// ── Funções ───────────────────────────────────────────────────────────────────
onMounted(load)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [membersRes, rolesRes] = await Promise.all([listMemberships(true), listRoles()])
    members.value = membersRes.data
    roles.value = rolesRes.data
  } catch {
    error.value = 'Não foi possível carregar os membros.'
  } finally {
    loading.value = false
  }
}

function startEditRole(member: Membership) {
  editingId.value = member.id
  editRole.value = member.role
  // custom permissions = resolved - preset (additions only)
  const preset = roles.value.find((r) => r.role === member.role)?.permissions ?? []
  editCustomPerms.value = (member.permissions ?? []).filter((p) => !preset.includes(p))
  editError.value = ''
}

function cancelEditRole() {
  editingId.value = null
  editCustomPerms.value = []
  editError.value = ''
}

function isFromPreset(perm: string): boolean {
  return editRolePresetPerms.value.includes(perm)
}

function isChecked(perm: string): boolean {
  return isFromPreset(perm) || editCustomPerms.value.includes(perm)
}

function toggleCustomPerm(perm: string) {
  if (isFromPreset(perm)) return // não pode remover permissões do preset
  const idx = editCustomPerms.value.indexOf(perm)
  if (idx === -1) editCustomPerms.value.push(perm)
  else editCustomPerms.value.splice(idx, 1)
}

// Quando o role muda, limpa custom perms que o novo preset já inclui
function onRoleChange() {
  const newPreset = roles.value.find((r) => r.role === editRole.value)?.permissions ?? []
  editCustomPerms.value = editCustomPerms.value.filter((p) => !newPreset.includes(p))
}

async function saveAuthorization(membershipId: number) {
  editLoading.value = true
  editError.value = ''
  try {
    await updateMembershipAuthorization(membershipId, editRole.value, editCustomPerms.value)
    await load()
    editingId.value = null
  } catch (e) {
    if (e instanceof ApiError) {
      const d = e.data as { message?: string; errors?: Record<string, string[]> }
      editError.value = (d.errors ? Object.values(d.errors)[0]?.[0] : null) ?? d.message ?? 'Erro ao salvar.'
    } else {
      editError.value = 'Erro inesperado.'
    }
  } finally {
    editLoading.value = false
  }
}

async function submitInvite() {
  inviteLoading.value = true
  inviteError.value = ''
  inviteSuccess.value = ''
  try {
    const res = await inviteMembership(inviteForm.value)
    generatedInviteUrl.value = res.data.invitation.accept_url
    inviteSuccess.value = 'Convite criado com sucesso.'
    inviteForm.value = { name: '', email: '', role: 'technician' }
    await load()
  } catch (e) {
    if (e instanceof ApiError) {
      const d = e.data as { message?: string; errors?: Record<string, string[]> }
      inviteError.value = (d.errors ? Object.values(d.errors)[0]?.[0] : null) ?? d.message ?? 'Erro.'
    } else {
      inviteError.value = 'Erro inesperado.'
    }
  } finally {
    inviteLoading.value = false
  }
}

async function toggleStatus(member: Membership) {
  statusLoadingId.value = member.id
  error.value = ''
  try {
    const next = member.status === 'suspended' ? 'active' : 'suspended'
    await updateMembershipStatus(member.id, next)
    await load()
  } catch (e) {
    error.value = (e instanceof ApiError ? (e.data as { message?: string })?.message : null) ?? 'Erro ao atualizar status.'
  } finally {
    statusLoadingId.value = null
  }
}

async function doRemove(password: string) {
  if (!removeModal.value) return
  removeLoading.value = true
  removeError.value = ''
  try {
    await removeMembership(removeModal.value.member.id, password)
    removeModal.value = null
    await load()
  } catch (e) {
    if (e instanceof ApiError) {
      const d = e.data as { message?: string; errors?: Record<string, string[]> }
      removeError.value = (d.errors ? Object.values(d.errors)[0]?.[0] : null) ?? d.message ?? 'Erro ao remover.'
    } else {
      removeError.value = 'Erro inesperado.'
    }
  } finally {
    removeLoading.value = false
  }
}

async function copyInviteUrl() {
  await navigator.clipboard.writeText(generatedInviteUrl.value).catch(() => {})
}

function permsByGroup(group: { prefix: string }) {
  return allPermissions.value.filter((p) => p.startsWith(group.prefix + '.'))
}
</script>

<template>
  <div class="space-y-6">
    <!-- Cabeçalho -->
    <div>
      <h1 class="text-2xl font-semibold" style="color: var(--c-text)">Usuários</h1>
      <p class="mt-1 text-sm" style="color: var(--c-text-muted)">
        Controle quem acessa o sistema, qual papel cada pessoa exerce e quem ainda precisa aceitar o convite.
      </p>
    </div>

    <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ error }}</div>

    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">

      <!-- ── Coluna esquerda: lista ────────────────────────────────────────── -->
      <div class="space-y-4">

        <!-- Busca e filtros -->
        <div class="card space-y-3">
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Buscar por nome, e-mail ou telefone…"
            class="search-input"
          />

          <div class="flex flex-wrap gap-2">
            <!-- Filtro de status -->
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="[val, label] in ([['', 'Todos'], ['active', 'Ativos'], ['invited', 'Convidados'], ['suspended', 'Suspensos']] as const)"
                :key="val"
                class="filter-chip"
                :class="{ 'filter-chip-active': statusFilter === val }"
                @click="statusFilter = val"
              >{{ label }}</button>
            </div>

            <!-- Filtro de role -->
            <select v-model="roleFilter" class="filter-select">
              <option value="">Todos os perfis</option>
              <option v-for="(label, key) in roleLabels" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>

          <!-- Ordenação -->
          <div class="flex flex-wrap items-center gap-2 border-t pt-2" style="border-color: var(--c-border)">
            <span class="text-xs" style="color: var(--c-text-dim)">Ordenar:</span>
            <button
              v-for="[col, lbl] in ([['user_name', 'Nome'], ['role', 'Perfil'], ['status', 'Status']] as const)"
              :key="col"
              class="sort-chip"
              :class="{ 'sort-chip-active': sortState(col) }"
              @click="toggleSort(col)"
            >
              {{ lbl }}
              <span v-if="sortState(col) === 'asc'">↑</span>
              <span v-else-if="sortState(col) === 'desc'">↓</span>
            </button>
          </div>
        </div>

        <!-- Lista de membros -->
        <div v-if="loading" class="card py-12 text-center text-sm" style="color: var(--c-text-dim)">Carregando…</div>

        <div v-else-if="filteredMembers.length === 0" class="card py-10 text-center text-sm" style="color: var(--c-text-muted)">
          Nenhum membro encontrado.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="member in filteredMembers"
            :key="member.id"
            class="member-card"
            :class="{ 'member-card-editing': editingId === member.id }"
          >
            <!-- Cabeçalho do card -->
            <div class="flex items-start gap-3">
              <div class="avatar">{{ (member.user_name ?? '?')[0].toUpperCase() }}</div>

              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="text-sm font-semibold" style="color: var(--c-text)">{{ member.user_name ?? 'Sem nome' }}</p>
                  <span class="badge" :class="statusClasses[member.status] ?? 'badge-default'">
                    {{ statusLabels[member.status] ?? member.status }}
                  </span>
                </div>
                <p class="mt-0.5 text-xs" style="color: var(--c-text-muted)">{{ member.user_email ?? '—' }}</p>
                <p v-if="member.user_phone" class="text-xs" style="color: var(--c-text-dim)">{{ phoneMask(member.user_phone) }}</p>

                <!-- Role badge (quando não está editando) -->
                <div v-if="editingId !== member.id" class="mt-2 flex flex-wrap items-center gap-1.5">
                  <span class="role-badge">{{ roleLabels[member.role] ?? member.role }}</span>
                  <span class="text-xs" style="color: var(--c-text-dim)">· {{ roleDescriptions[member.role] ?? '—' }}</span>
                </div>

                <!-- Permissões adicionais (quando não está editando e tem extras) -->
                <div v-if="editingId !== member.id && member.permissions.length > 0" class="mt-1.5 flex flex-wrap gap-1">
                  <span
                    v-for="p in member.permissions"
                    :key="p"
                    class="perm-chip-extra"
                    :title="permissionLabels[p] ?? p"
                  >+ {{ permissionLabels[p] ?? p }}</span>
                </div>
              </div>

              <!-- Ações (quando não está editando) -->
              <div v-if="editingId !== member.id" class="flex flex-wrap gap-1.5 shrink-0">
                <button class="btn-ghost text-xs" @click="editModal = member">Dados</button>
                <button class="btn-ghost text-xs" @click="startEditRole(member)">Perfil</button>
                <button
                  v-if="member.status !== 'invited'"
                  :disabled="statusLoadingId === member.id"
                  class="btn-ghost text-xs disabled:opacity-50"
                  :class="member.status === 'suspended' ? 'hover:!text-emerald-400' : 'hover:!text-orange-400'"
                  @click="toggleStatus(member)"
                >{{ statusLoadingId === member.id ? '…' : member.status === 'suspended' ? 'Reativar' : 'Suspender' }}</button>
                <button class="btn-ghost text-xs hover:!text-red-400" @click="removeModal = { member }; removeError = ''">Remover</button>
              </div>
            </div>

            <!-- Editor de perfil + permissões ──────────────────────────── -->
            <div v-if="editingId === member.id" class="mt-4 space-y-4 border-t pt-4" style="border-color: var(--c-border)">

              <!-- Seletor de role -->
              <div class="space-y-1.5">
                <label class="field-label">Perfil base</label>
                <select v-model="editRole" class="input" @change="onRoleChange">
                  <option v-for="(label, key) in roleLabels" :key="key" :value="key">{{ label }}</option>
                </select>
                <p class="text-xs" style="color: var(--c-text-dim)">{{ roleDescriptions[editRole] }}</p>
              </div>

              <!-- Editor de permissões por categoria -->
              <div class="space-y-3">
                <p class="field-label">Permissões efetivas</p>
                <p class="text-xs" style="color: var(--c-text-dim)">
                  Permissões em <span class="text-amber-400">âmbar</span> vêm do perfil base e não podem ser removidas individualmente.
                  Você pode adicionar permissões extras marcando as caixas abaixo.
                </p>

                <div
                  v-for="group in permissionGroups"
                  :key="group.prefix"
                  class="rounded-xl border p-3 space-y-2"
                  style="border-color: var(--c-border); background: var(--c-card)"
                >
                  <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--c-text-muted)">{{ group.label }}</p>
                  <div class="grid gap-1.5 sm:grid-cols-2">
                    <label
                      v-for="perm in permsByGroup(group)"
                      :key="perm"
                      class="perm-row"
                      :class="isFromPreset(perm) ? 'perm-row-preset' : ''"
                      :title="isFromPreset(perm) ? 'Incluída pelo perfil base' : ''"
                    >
                      <input
                        type="checkbox"
                        :checked="isChecked(perm)"
                        :disabled="isFromPreset(perm)"
                        class="perm-check"
                        @change="toggleCustomPerm(perm)"
                      />
                      <span class="text-xs" :class="isFromPreset(perm) ? 'text-amber-400' : ''">
                        {{ permissionLabels[perm] ?? perm }}
                      </span>
                    </label>
                  </div>
                </div>
              </div>

              <p v-if="editError" class="text-xs text-red-400">{{ editError }}</p>

              <div class="flex gap-2">
                <button :disabled="editLoading" class="btn-primary text-xs" @click="saveAuthorization(member.id)">
                  {{ editLoading ? 'Salvando…' : 'Salvar perfil' }}
                </button>
                <button class="btn-ghost text-xs" @click="cancelEditRole">Cancelar</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Coluna direita ─────────────────────────────────────────────────── -->
      <div class="space-y-4">

        <!-- Convidar membro -->
        <section class="card space-y-4">
          <h2 class="section-title">Convidar membro</h2>

          <div v-if="inviteError" class="rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300">{{ inviteError }}</div>
          <div v-if="inviteSuccess" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-300">{{ inviteSuccess }}</div>

          <form class="space-y-3" @submit.prevent="submitInvite">
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="space-y-1.5">
                <span class="field-label">Nome</span>
                <input v-model="inviteForm.name" class="input" type="text" placeholder="Nome completo" required />
              </label>
              <label class="space-y-1.5">
                <span class="field-label">E-mail</span>
                <input v-model="inviteForm.email" class="input" type="email" placeholder="email@empresa.com" required />
              </label>
            </div>
            <label class="space-y-1.5">
              <span class="field-label">Perfil inicial</span>
              <select v-model="inviteForm.role" class="input" required>
                <option v-for="(label, key) in roleLabels" :key="key" :value="key">{{ label }}</option>
              </select>
              <p class="text-xs" style="color: var(--c-text-dim)">{{ roleDescriptions[inviteForm.role] }}</p>
            </label>
            <button type="submit" :disabled="inviteLoading" class="btn-primary w-full">
              {{ inviteLoading ? 'Convidando…' : 'Enviar convite' }}
            </button>
          </form>

          <div v-if="generatedInviteUrl" class="rounded-xl border p-3 space-y-2" style="border-color: var(--c-border); background: var(--c-card)">
            <p class="field-label">Link do convite</p>
            <div class="flex gap-2">
              <input :value="generatedInviteUrl" readonly class="input flex-1 text-xs" />
              <button type="button" class="btn-ghost text-xs whitespace-nowrap" @click="copyInviteUrl">Copiar</button>
            </div>
          </div>
        </section>

        <!-- Referência de perfis -->
        <section class="card space-y-3">
          <h2 class="section-title">Perfis disponíveis</h2>
          <div v-for="(desc, key) in roleDescriptions" :key="key" class="text-xs space-y-0.5">
            <p class="font-semibold text-amber-400">{{ roleLabels[key] }}</p>
            <p style="color: var(--c-text-dim)">{{ desc }}</p>
          </div>
        </section>
      </div>
    </div>

    <!-- Modal: editar dados pessoais -->
    <EditMemberModal
      v-if="editModal"
      :member="editModal"
      @saved="load"
      @close="editModal = null"
    />

    <!-- Modal: remover membro -->
    <PasswordConfirmModal
      v-if="removeModal"
      :title="`Remover ${removeModal.member.user_name ?? 'membro'}`"
      :description="`Esta ação remove o acesso de ${removeModal.member.user_email ?? 'este usuário'} ao sistema. Confirme sua senha para prosseguir.`"
      confirm-label="Remover membro"
      :loading="removeLoading"
      :error="removeError"
      @confirm="doRemove"
      @cancel="removeModal = null"
    />
  </div>
</template>

<style scoped>
@reference "../../style.css";

.card {
  @apply rounded-2xl border p-5;
  background: var(--c-surface);
  border-color: var(--c-border);
}

.section-title {
  @apply text-sm font-semibold uppercase tracking-widest;
  color: var(--c-text-muted);
}

.field-label {
  @apply block text-xs font-medium uppercase tracking-wider;
  color: var(--c-text-muted);
}

/* Busca */
.search-input {
  @apply w-full rounded-xl border px-3 py-2.5 text-sm outline-none transition;
  color: var(--c-text);
  background: var(--c-input-bg);
  border-color: var(--c-input-border);
}
.search-input:focus { border-color: rgba(251,146,60,.5); box-shadow: 0 0 0 2px rgba(251,146,60,.08); }
.search-input::placeholder { color: var(--c-text-dim); }

/* Filtros */
.filter-chip {
  @apply rounded-full border px-3 py-0.5 text-xs font-medium transition;
  border-color: var(--c-border);
  color: var(--c-text-muted);
}
.filter-chip:hover { color: var(--c-text); border-color: var(--c-border-strong); }
.filter-chip-active {
  border-color: rgba(251,146,60,.4) !important;
  color: #fbbf24 !important;
  background: rgba(251,146,60,.08);
}
.filter-select {
  @apply rounded-xl border px-3 py-1 text-xs outline-none transition;
  color: var(--c-text-muted);
  background: var(--c-input-bg);
  border-color: var(--c-input-border);
}
.filter-select:focus { border-color: rgba(251,146,60,.5); }

/* Ordenação */
.sort-chip {
  @apply rounded-full border px-3 py-0.5 text-xs font-medium transition select-none cursor-pointer;
  border-color: var(--c-border);
  color: var(--c-text-muted);
}
.sort-chip:hover { color: var(--c-text); border-color: var(--c-border-strong); }
.sort-chip-active { border-color: rgba(251,146,60,.4) !important; color: #fbbf24 !important; background: rgba(251,146,60,.08); }

/* Cards de membro */
.member-card {
  @apply rounded-2xl border px-4 py-4 transition;
  background: var(--c-card);
  border-color: var(--c-border);
}
.member-card-editing {
  border-color: rgba(251,146,60,.3);
  background: rgba(251,146,60,.03);
}

/* Avatar */
.avatar {
  @apply flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold;
  background: rgba(245,158,11,.15);
  color: #fbbf24;
}

/* Badges de status */
.badge { @apply rounded-full px-2 py-0.5 text-xs font-medium; }
.badge-active   { background: rgba(16,185,129,.12); color: var(--c-badge-emerald, #34d399); }
.badge-invited  { background: rgba(14,165,233,.12);  color: var(--c-badge-sky, #38bdf8); }
.badge-suspended{ background: rgba(239,68,68,.12);   color: var(--c-badge-red, #f87171); }
.badge-default  { @apply bg-stone-500/10 text-stone-400; }

/* Role badge */
.role-badge {
  @apply rounded-full px-2 py-0.5 text-xs font-semibold;
  background: rgba(245,158,11,.1);
  color: #fbbf24;
}

/* Permissão extra chip */
.perm-chip-extra {
  @apply rounded px-1.5 py-0.5 text-[10px] font-medium;
  background: rgba(99,102,241,.1);
  color: #a5b4fc;
  border: 1px solid rgba(99,102,241,.2);
}

/* Editor de permissões */
.perm-row {
  @apply flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 transition;
  color: var(--c-text-muted);
}
.perm-row:hover { background: rgba(255,255,255,.04); }
.perm-row-preset { cursor: default; }
.perm-row-preset:hover { background: transparent; }
.perm-check { @apply h-3.5 w-3.5 rounded accent-amber-500; }

/* Input */
.input {
  @apply w-full rounded-xl border px-3 py-2 text-sm outline-none transition;
  color: var(--c-text);
  background: var(--c-input-bg);
  border-color: var(--c-input-border);
}
.input:focus { border-color: rgba(251,146,60,.5); box-shadow: 0 0 0 2px rgba(251,146,60,.08); }
.input::placeholder { color: var(--c-text-dim); }

/* Botões */
.btn-primary {
  @apply rounded-xl px-3 py-1.5 text-sm font-semibold transition disabled:opacity-50;
  background: #f59e0b; color: #1c1917;
}
.btn-primary:hover:not(:disabled) { background: #fbbf24; }

.btn-ghost {
  @apply rounded-lg border px-3 py-1.5 text-sm font-medium transition;
  border-color: var(--c-border);
  color: var(--c-text-muted);
}
.btn-ghost:hover { border-color: var(--c-border-strong); color: var(--c-text); }
</style>
