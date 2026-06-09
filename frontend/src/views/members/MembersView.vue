<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { inviteMembership, listMemberships, type Membership, updateMembershipStatus } from '../../api/memberships'
import { apiPatch } from '../../api/client'
import { ApiError } from '../../api/client'

const loading = ref(true)
const members = ref<Membership[]>([])
const error = ref('')
const inviteSuccess = ref('')
const generatedInviteUrl = ref('')
const editingId = ref<number | null>(null)
const editRole = ref('')
const editLoading = ref(false)
const editError = ref('')
const inviteForm = ref({
  name: '',
  email: '',
  role: 'technician',
})
const inviteLoading = ref(false)
const inviteError = ref('')
const statusLoadingId = ref<number | null>(null)

const roleLabels: Record<string, string> = {
  administrator: 'Administrador',
  manager: 'Gerente',
  attendant: 'Atendente',
  technician: 'Técnico',
  finance: 'Financeiro',
}

const statusLabels: Record<string, string> = {
  active: 'Ativo',
  invited: 'Convidado',
  suspended: 'Suspenso',
}

onMounted(async () => {
  await load()
})

async function load() {
  try {
    const res = await listMemberships(true)
    members.value = res.data
  } catch {
    error.value = 'Não foi possível carregar os membros.'
  } finally {
    loading.value = false
  }
}

function startEdit(m: Membership) {
  editingId.value = m.id
  editRole.value = m.role
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
      const errData = e.data as { message?: string; errors?: Record<string, string[]> }
      const first = errData.errors ? Object.values(errData.errors)[0]?.[0] : null
      editError.value = first ?? errData.message ?? 'Erro ao salvar.'
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
    inviteForm.value = {
      name: '',
      email: '',
      role: 'technician',
    }
    await load()
  } catch (e) {
    if (e instanceof ApiError) {
      const errData = e.data as { message?: string; errors?: Record<string, string[]> }
      const first = errData.errors ? Object.values(errData.errors)[0]?.[0] : null
      inviteError.value = first ?? errData.message ?? 'Erro ao criar convite.'
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
    if (e instanceof ApiError) {
      error.value = (e.data as { message?: string })?.message ?? 'Erro ao atualizar status.'
    } else {
      error.value = 'Erro inesperado.'
    }
  } finally {
    statusLoadingId.value = null
  }
}

async function copyInviteUrl() {
  if (!generatedInviteUrl.value) return
  await navigator.clipboard.writeText(generatedInviteUrl.value).catch(() => {})
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-white">Membros</h1>
    </div>

    <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ error }}</div>

    <div v-if="inviteError" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ inviteError }}</div>
    <div v-if="inviteSuccess" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
      {{ inviteSuccess }}
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
      <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-400">Convidar membro</h2>
      <form class="grid gap-4 md:grid-cols-4" @submit.prevent="submitInvite">
        <input v-model="inviteForm.name" class="input" type="text" placeholder="Nome" required />
        <input v-model="inviteForm.email" class="input" type="email" placeholder="E-mail" required />
        <select v-model="inviteForm.role" class="input" required>
          <option v-for="(label, key) in roleLabels" :key="key" :value="key">{{ label }}</option>
        </select>
        <button
          type="submit"
          :disabled="inviteLoading"
          class="rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
        >
          {{ inviteLoading ? 'Convidando…' : 'Enviar convite' }}
        </button>
      </form>
      <div v-if="generatedInviteUrl" class="mt-4 rounded-xl border border-white/10 bg-black/20 p-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Link do convite</p>
        <div class="mt-2 flex gap-2">
          <input :value="generatedInviteUrl" readonly class="input flex-1" />
          <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-300 hover:border-amber-500/30 hover:text-amber-300" @click="copyInviteUrl">
            Copiar
          </button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

    <div v-else class="rounded-2xl border border-white/10 bg-white/5 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-white/10 text-left">
            <th class="px-6 py-3.5 text-xs font-semibold uppercase tracking-widest text-stone-500">Nome</th>
            <th class="px-6 py-3.5 text-xs font-semibold uppercase tracking-widest text-stone-500">E-mail</th>
            <th class="px-6 py-3.5 text-xs font-semibold uppercase tracking-widest text-stone-500">Perfil</th>
            <th class="px-6 py-3.5 text-xs font-semibold uppercase tracking-widest text-stone-500">Status</th>
            <th class="px-6 py-3.5"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="m in members"
            :key="m.id"
            class="border-b border-white/5 transition hover:bg-white/2"
          >
            <td class="px-6 py-4 text-stone-200">{{ m.user_name ?? '—' }}</td>
            <td class="px-6 py-4 text-stone-400">{{ m.user_email ?? '—' }}</td>
            <td class="px-6 py-4">
              <template v-if="editingId === m.id">
                <select v-model="editRole" class="input !w-auto">
                  <option v-for="(label, key) in roleLabels" :key="key" :value="key">{{ label }}</option>
                </select>
                <p v-if="editError" class="mt-1 text-xs text-red-400">{{ editError }}</p>
              </template>
              <span v-else class="rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-300">
                {{ roleLabels[m.role] ?? m.role }}
              </span>
            </td>
            <td class="px-6 py-4">
              <span
                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                :class="m.status === 'active'
                  ? 'bg-emerald-500/10 text-emerald-400'
                  : m.status === 'suspended'
                    ? 'bg-red-500/10 text-red-400'
                    : 'bg-stone-500/10 text-stone-400'"
              >
                {{ statusLabels[m.status] ?? m.status }}
              </span>
            </td>
            <td class="px-6 py-4 text-right">
              <template v-if="editingId === m.id">
                <button
                  :disabled="editLoading"
                  class="mr-2 text-xs text-amber-400 hover:text-amber-300 disabled:opacity-50"
                  @click="saveRole(m.id)"
                >
                  {{ editLoading ? 'Salvando…' : 'Salvar' }}
                </button>
                <button class="text-xs text-stone-500 hover:text-stone-300" @click="cancelEdit">Cancelar</button>
              </template>
              <template v-else>
                <button
                  class="mr-3 text-xs text-stone-500 hover:text-amber-400"
                  @click="startEdit(m)"
                >
                  Editar perfil
                </button>
                <button
                  v-if="m.status !== 'invited'"
                  :disabled="statusLoadingId === m.id"
                  class="text-xs"
                  :class="m.status === 'suspended' ? 'text-emerald-400 hover:text-emerald-300' : 'text-red-400 hover:text-red-300'"
                  @click="toggleStatus(m)"
                >
                  {{
                    statusLoadingId === m.id
                      ? 'Salvando…'
                      : m.status === 'suspended'
                        ? 'Reativar'
                        : 'Suspender'
                  }}
                </button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.input {
  @apply w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20;
}
</style>
