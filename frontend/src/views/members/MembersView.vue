<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { inviteMembership, listMemberships, removeMembership, type Membership, updateMembershipStatus } from '../../api/memberships'
import { apiPatch, ApiError } from '../../api/client'
import PasswordConfirmModal from '../../components/PasswordConfirmModal.vue'
import EditMemberModal from '../../components/EditMemberModal.vue'
import { useSortable } from '../../composables/useSortable'

const loading = ref(true)
const members = ref<Membership[]>([])
const error = ref('')
const inviteSuccess = ref('')
const generatedInviteUrl = ref('')
const editingId = ref<number | null>(null)
const editRole = ref('')
const editLoading = ref(false)
const editError = ref('')
const inviteForm = ref({ name: '', email: '', role: 'technician' })
const inviteLoading = ref(false)
const inviteError = ref('')
const statusLoadingId = ref<number | null>(null)

const { sorted: sortedMembers, toggleSort: memberToggle, sortState: memberState } = useSortable(members)

const editModal = ref<Membership | null>(null)
const removeModal = ref<{ member: Membership } | null>(null)
const removeLoading = ref(false)
const removeError = ref('')

const roleLabels: Record<string, string> = {
  administrator: 'Administrador',
  manager: 'Gerente',
  attendant: 'Atendente',
  technician: 'Técnico',
  finance: 'Financeiro',
}

const roleDescriptions: Record<string, string> = {
  administrator: 'Administra membros, configurações e toda a operação da empresa.',
  manager: 'Coordena a operação completa, sem gestão de membros.',
  attendant: 'Cuida de cadastro, abertura e acompanhamento comercial da O.S.',
  technician: 'Atua somente nas O.S. atribuídas, com diagnóstico e execução.',
  finance: 'Acompanha recebimentos e liberações financeiras em todo o escopo visível.',
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

onMounted(load)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await listMemberships(true)
    members.value = res.data
  } catch {
    error.value = 'Não foi possível carregar os membros.'
  } finally {
    loading.value = false
  }
}

function startEdit(membership: Membership) {
  editingId.value = membership.id
  editRole.value = membership.role
  editError.value = ''
}

function cancelEdit() {
  editingId.value = null
  editRole.value = ''
  editError.value = ''
}

async function saveRole(membershipId: number) {
  editLoading.value = true
  editError.value = ''
  try {
    await apiPatch(`/tenant/memberships/${membershipId}/authorization`, { role: editRole.value, permissions: [] })
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
      inviteError.value = (d.errors ? Object.values(d.errors)[0]?.[0] : null) ?? d.message ?? 'Erro ao criar convite.'
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
    const nextStatus = member.status === 'suspended' ? 'active' : 'suspended'
    await updateMembershipStatus(member.id, nextStatus)
    await load()
  } catch (e) {
    error.value = (e instanceof ApiError ? (e.data as { message?: string })?.message : null) ?? 'Erro ao atualizar status.'
  } finally {
    statusLoadingId.value = null
  }
}

function openRemoveModal(member: Membership) {
  removeModal.value = { member }
  removeError.value = ''
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
  if (!generatedInviteUrl.value) return
  await navigator.clipboard.writeText(generatedInviteUrl.value).catch(() => {})
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold" style="color: var(--c-text)">Usuários</h1>
      <p class="mt-1 text-sm" style="color: var(--c-text-muted)">
        Controle quem acessa o sistema, qual papel cada pessoa exerce e quem ainda precisa aceitar convite.
      </p>
    </div>

    <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ error }}</div>

    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">

      <!-- Lista de membros -->
      <section class="card space-y-4">
        <h2 class="section-title">Equipe atual</h2>

        <!-- Cabeçalho de ordenação -->
        <div class="sort-strip">
          <span style="color: var(--c-text-dim); font-size: 11px">Ordenar por:</span>
          <button
            v-for="col in ([['user_name', 'Nome'], ['role', 'Perfil'], ['status', 'Status']] as const)"
            :key="col[0]"
            class="sort-chip"
            :class="{ 'sort-chip-active': memberState(col[0]) }"
            @click="memberToggle(col[0])"
          >
            {{ col[1] }}
            <span v-if="memberState(col[0]) === 'asc'">↑</span>
            <span v-else-if="memberState(col[0]) === 'desc'">↓</span>
          </button>
        </div>

        <div v-if="loading" class="py-12 text-center text-sm" style="color: var(--c-text-dim)">Carregando…</div>

        <div v-else-if="members.length === 0" class="rounded-xl border px-6 py-10 text-center text-sm" style="border-color: var(--c-border); color: var(--c-text-muted)">
          Nenhum membro encontrado.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="member in sortedMembers"
            :key="member.id"
            class="member-row"
          >
            <div class="flex flex-1 min-w-0 flex-col gap-2">
              <!-- Cabeçalho do membro -->
              <div class="flex flex-wrap items-center gap-2">
                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-[11px] font-semibold text-amber-400">
                  {{ (member.user_name ?? '?')[0].toUpperCase() }}
                </div>
                <p class="text-sm font-semibold" style="color: var(--c-text)">{{ member.user_name ?? 'Sem nome' }}</p>
                <span class="badge" :class="statusClasses[member.status] ?? 'badge-default'">
                  {{ statusLabels[member.status] ?? member.status }}
                </span>
              </div>
              <p class="text-xs pl-9" style="color: var(--c-text-muted)">{{ member.user_email ?? '—' }}</p>

              <!-- Edição de perfil -->
              <template v-if="editingId === member.id">
                <div class="mt-1 space-y-2 pl-9">
                  <select v-model="editRole" class="input max-w-xs">
                    <option v-for="(label, key) in roleLabels" :key="key" :value="key">{{ label }}</option>
                  </select>
                  <p class="text-xs" style="color: var(--c-text-dim)">{{ roleDescriptions[editRole] }}</p>
                  <p v-if="editError" class="text-xs text-red-400">{{ editError }}</p>
                </div>
              </template>
              <template v-else>
                <div class="pl-9 flex flex-wrap items-center gap-1.5">
                  <span class="rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-400">
                    {{ roleLabels[member.role] ?? member.role }}
                  </span>
                  <span class="text-xs" style="color: var(--c-text-dim)">· {{ roleDescriptions[member.role] ?? '—' }}</span>
                </div>
              </template>
            </div>

            <!-- Ações -->
            <div class="flex flex-wrap gap-1.5 shrink-0 self-start mt-1">
              <template v-if="editingId === member.id">
                <button :disabled="editLoading" class="btn-primary text-xs" @click="saveRole(member.id)">
                  {{ editLoading ? 'Salvando…' : 'Salvar' }}
                </button>
                <button class="btn-ghost text-xs" @click="cancelEdit">Cancelar</button>
              </template>
              <template v-else>
                <button class="btn-ghost text-xs" @click="editModal = member">Editar dados</button>
                <button class="btn-ghost text-xs" @click="startEdit(member)">Editar perfil</button>
                <button
                  v-if="member.status !== 'invited'"
                  :disabled="statusLoadingId === member.id"
                  class="btn-ghost text-xs disabled:opacity-50"
                  :class="member.status === 'suspended' ? 'hover:!text-emerald-400' : 'hover:!text-orange-400'"
                  @click="toggleStatus(member)"
                >
                  {{ statusLoadingId === member.id ? '…' : member.status === 'suspended' ? 'Reativar' : 'Suspender' }}
                </button>
                <button class="btn-ghost text-xs hover:!text-red-400" @click="openRemoveModal(member)">Remover</button>
              </template>
            </div>
          </div>
        </div>
      </section>

      <!-- Painel direito -->
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

        <!-- Info sobre perfis -->
        <section class="card space-y-3">
          <h2 class="section-title">Perfis de acesso</h2>
          <div v-for="(desc, key) in roleDescriptions" :key="key" class="text-xs space-y-0.5">
            <p class="font-semibold text-amber-400">{{ roleLabels[key] }}</p>
            <p style="color: var(--c-text-dim)">{{ desc }}</p>
          </div>
        </section>
      </div>
    </div>

    <!-- Modal: editar dados do membro -->
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

.member-row {
  @apply flex items-start gap-3 rounded-xl border px-4 py-3;
  border-color: var(--c-border);
  background: var(--c-card);
}

.badge {
  @apply rounded-full px-2 py-0.5 text-xs font-medium;
}

.badge-active { @apply bg-emerald-500/10 text-emerald-400; }
.badge-invited { @apply bg-sky-500/10 text-sky-300; }
.badge-suspended { @apply bg-red-500/10 text-red-400; }
.badge-default { @apply bg-stone-500/10 text-stone-400; }

.input {
  @apply w-full rounded-xl border px-3 py-2 text-sm outline-none transition;
  color: var(--c-text);
  background: var(--c-input-bg);
  border-color: var(--c-input-border);
}

.input:focus {
  border-color: rgba(251, 146, 60, 0.5);
  box-shadow: 0 0 0 2px rgba(251, 146, 60, 0.08);
}

.input::placeholder { color: var(--c-text-dim); }

.btn-primary {
  @apply rounded-xl px-3 py-1.5 text-sm font-semibold transition disabled:opacity-50;
  background: #f59e0b;
  color: #1c1917;
}

.btn-primary:hover { background: #fbbf24; }

.btn-ghost {
  @apply rounded-lg border px-3 py-1.5 text-sm font-medium transition;
  border-color: var(--c-border);
  color: var(--c-text-muted);
}

.btn-ghost:hover {
  border-color: var(--c-border-strong);
  color: var(--c-text);
}

.sort-strip {
  @apply flex flex-wrap items-center gap-2 pb-1;
}

.sort-chip {
  @apply rounded-full border px-3 py-0.5 text-xs font-medium transition select-none cursor-pointer;
  border-color: var(--c-border);
  color: var(--c-text-muted);
}

.sort-chip:hover {
  color: var(--c-text);
  border-color: var(--c-border-strong);
}

.sort-chip-active {
  border-color: rgba(251, 146, 60, 0.4) !important;
  color: #fbbf24 !important;
  background: rgba(251, 146, 60, 0.08);
}
</style>
