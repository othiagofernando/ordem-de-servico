<script setup lang="ts">
import { onMounted, ref } from 'vue'
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
  active: 'bg-emerald-500/10 text-emerald-400',
  invited: 'bg-sky-500/10 text-sky-300',
  suspended: 'bg-red-500/10 text-red-400',
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
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Membros</h1>
        <p class="mt-1 text-sm text-stone-400">
          Controle quem participa da empresa, qual papel cada pessoa exerce e quem ainda precisa aceitar convite.
        </p>
      </div>
    </div>

    <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ error }}</div>
    <div v-if="inviteError" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ inviteError }}</div>
    <div v-if="inviteSuccess" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
      {{ inviteSuccess }}
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-400">Equipe atual</h2>

        <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

        <div v-else-if="members.length === 0" class="rounded-xl border border-white/10 bg-black/20 p-8 text-center text-stone-400">
          Nenhum membro encontrado para esta empresa.
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="member in members"
            :key="member.id"
            class="rounded-xl border border-white/10 bg-black/20 p-4"
          >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="text-sm font-semibold text-stone-100">{{ member.user_name ?? 'Sem nome' }}</p>
                  <span
                    class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                    :class="statusClasses[member.status] ?? 'bg-stone-500/10 text-stone-400'"
                  >
                    {{ statusLabels[member.status] ?? member.status }}
                  </span>
                </div>
                <p class="text-sm text-stone-400">{{ member.user_email ?? '—' }}</p>

                <template v-if="editingId === member.id">
                  <div class="space-y-2">
                    <label class="block text-xs font-semibold uppercase tracking-widest text-stone-500">Perfil</label>
                    <select v-model="editRole" class="input max-w-xs">
                      <option v-for="(label, key) in roleLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p class="text-xs text-stone-500">{{ roleDescriptions[editRole] ?? 'Sem descrição disponível.' }}</p>
                    <p v-if="editError" class="text-xs text-red-400">{{ editError }}</p>
                  </div>
                </template>

                <template v-else>
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-300">
                      {{ roleLabels[member.role] ?? member.role }}
                    </span>
                  </div>
                  <p class="text-xs text-stone-500">{{ roleDescriptions[member.role] ?? 'Sem descrição disponível.' }}</p>
                </template>
              </div>

              <div class="flex flex-wrap gap-2 lg:justify-end">
                <template v-if="editingId === member.id">
                  <button
                    :disabled="editLoading"
                    class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-stone-950 hover:bg-amber-400 disabled:opacity-50"
                    @click="saveRole(member.id)"
                  >
                    {{ editLoading ? 'Salvando…' : 'Salvar perfil' }}
                  </button>
                  <button class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-stone-300 hover:border-white/20" @click="cancelEdit">
                    Cancelar
                  </button>
                </template>

                <template v-else>
                  <button
                    class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-stone-300 hover:border-amber-500/30 hover:text-amber-300"
                    @click="startEdit(member)"
                  >
                    Editar perfil
                  </button>
                  <button
                    v-if="member.status !== 'invited'"
                    :disabled="statusLoadingId === member.id"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                    :class="member.status === 'suspended'
                      ? 'border border-emerald-500/20 text-emerald-400 hover:bg-emerald-500/10'
                      : 'border border-red-500/20 text-red-400 hover:bg-red-500/10'"
                    @click="toggleStatus(member)"
                  >
                    {{
                      statusLoadingId === member.id
                        ? 'Salvando…'
                        : member.status === 'suspended'
                          ? 'Reativar'
                          : 'Suspender'
                    }}
                  </button>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-400">Convidar membro</h2>
          <form class="space-y-4" @submit.prevent="submitInvite">
            <div class="grid gap-4 md:grid-cols-2">
              <label class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Nome</span>
                <input v-model="inviteForm.name" class="input" type="text" placeholder="Nome da pessoa" required />
              </label>
              <label class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">E-mail</span>
                <input v-model="inviteForm.email" class="input" type="email" placeholder="email@empresa.com" required />
              </label>
            </div>

            <label class="space-y-1">
              <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Perfil inicial</span>
              <select v-model="inviteForm.role" class="input" required>
                <option v-for="(label, key) in roleLabels" :key="key" :value="key">{{ label }}</option>
              </select>
              <p class="text-xs text-stone-500">{{ roleDescriptions[inviteForm.role] ?? 'Sem descrição disponível.' }}</p>
            </label>

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
              <button
                type="button"
                class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-300 hover:border-amber-500/30 hover:text-amber-300"
                @click="copyInviteUrl"
              >
                Copiar
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.input {
  @apply w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20;
}
</style>
