<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { listMemberships, type Membership } from '../../api/memberships'
import { apiPatch } from '../../api/client'
import { ApiError } from '../../api/client'

const loading = ref(true)
const members = ref<Membership[]>([])
const error = ref('')
const editingId = ref<number | null>(null)
const editRole = ref('')
const editLoading = ref(false)
const editError = ref('')

const roleLabels: Record<string, string> = {
  administrator: 'Administrador',
  manager: 'Gerente',
  attendant: 'Atendente',
  technician: 'Técnico',
  finance: 'Financeiro',
}

onMounted(async () => {
  await load()
})

async function load() {
  try {
    const res = await listMemberships()
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
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-white">Membros</h1>
    </div>

    <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ error }}</div>

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
                <select v-model="editRole" class="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-white focus:border-amber-500/50 outline-none">
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
                :class="m.status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-stone-500/10 text-stone-400'"
              >
                {{ m.status === 'active' ? 'Ativo' : m.status }}
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
              <button
                v-else
                class="text-xs text-stone-500 hover:text-amber-400"
                @click="startEdit(m)"
              >
                Editar perfil
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
