<script setup lang="ts">
import { ref, watch } from 'vue'
import { type Membership, resetMemberPassword, updateMemberUser } from '../api/memberships'
import { ApiError } from '../api/client'

const props = defineProps<{ member: Membership }>()
const emit = defineEmits<{ saved: []; close: [] }>()

type Tab = 'data' | 'password'
const activeTab = ref<Tab>('data')

// ── Dados pessoais ──────────────────────────
const dataForm = ref({ name: '', email: '', phone: '' })
const dataSaving = ref(false)
const dataSuccess = ref('')
const dataErrors = ref<Record<string, string>>({})

watch(
  () => props.member,
  (m) => {
    dataForm.value = { name: m.user_name ?? '', email: m.user_email ?? '', phone: m.user_phone ?? '' }
    dataSuccess.value = ''
    dataErrors.value = {}
  },
  { immediate: true },
)

async function saveData() {
  dataErrors.value = {}
  dataSuccess.value = ''
  dataSaving.value = true
  try {
    await updateMemberUser(props.member.id, {
      name: dataForm.value.name,
      email: dataForm.value.email,
      phone: dataForm.value.phone || null,
    })
    dataSuccess.value = 'Dados atualizados com sucesso.'
    emit('saved')
  } catch (e) {
    if (e instanceof ApiError) {
      const d = e.data as { errors?: Record<string, string[]>; message?: string }
      if (d.errors) {
        for (const [k, v] of Object.entries(d.errors)) dataErrors.value[k] = v[0]
      } else {
        dataErrors.value.general = d.message ?? 'Erro ao salvar.'
      }
    } else {
      dataErrors.value.general = 'Erro inesperado.'
    }
  } finally {
    dataSaving.value = false
  }
}

// ── Redefinir senha ─────────────────────────
const pwForm = ref({ admin_password: '', password: '', password_confirmation: '' })
const pwSaving = ref(false)
const pwSuccess = ref('')
const pwErrors = ref<Record<string, string>>({})

async function savePassword() {
  pwErrors.value = {}
  pwSuccess.value = ''

  if (pwForm.value.password !== pwForm.value.password_confirmation) {
    pwErrors.value.password_confirmation = 'As senhas não coincidem.'
    return
  }
  if (pwForm.value.password.length < 8) {
    pwErrors.value.password = 'Mínimo de 8 caracteres.'
    return
  }

  pwSaving.value = true
  try {
    await resetMemberPassword(
      props.member.id,
      pwForm.value.admin_password,
      pwForm.value.password,
      pwForm.value.password_confirmation,
    )
    pwSuccess.value = 'Senha redefinida com sucesso.'
    pwForm.value = { admin_password: '', password: '', password_confirmation: '' }
  } catch (e) {
    if (e instanceof ApiError) {
      const d = e.data as { errors?: Record<string, string[]>; message?: string }
      if (d.errors) {
        for (const [k, v] of Object.entries(d.errors)) pwErrors.value[k] = v[0]
      } else {
        pwErrors.value.general = d.message ?? 'Erro ao redefinir senha.'
      }
    } else {
      pwErrors.value.general = 'Erro inesperado.'
    }
  } finally {
    pwSaving.value = false
  }
}
</script>

<template>
  <!-- Backdrop -->
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-panel" role="dialog" aria-modal="true">

      <!-- Header -->
      <div class="modal-header">
        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-500/15 text-sm font-semibold text-amber-400">
          {{ (member.user_name ?? '?')[0].toUpperCase() }}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold truncate" style="color: var(--c-text)">{{ member.user_name ?? 'Usuário' }}</p>
          <p class="text-xs truncate" style="color: var(--c-text-muted)">{{ member.user_email }}</p>
        </div>
        <button class="modal-close" @click="emit('close')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Tabs -->
      <div class="modal-tabs">
        <button class="tab-btn" :class="{ 'tab-active': activeTab === 'data' }" @click="activeTab = 'data'">
          Dados pessoais
        </button>
        <button class="tab-btn" :class="{ 'tab-active': activeTab === 'password' }" @click="activeTab = 'password'">
          Redefinir senha
        </button>
      </div>

      <!-- Tab: Dados pessoais -->
      <div v-if="activeTab === 'data'" class="modal-body space-y-4">
        <div v-if="dataSuccess" class="alert-success">{{ dataSuccess }}</div>
        <div v-if="dataErrors.general" class="alert-error">{{ dataErrors.general }}</div>

        <div>
          <label class="field-label">Nome</label>
          <input v-model="dataForm.name" type="text" class="field-input" placeholder="Nome completo" />
          <p v-if="dataErrors.name" class="mt-1 text-xs text-red-400">{{ dataErrors.name }}</p>
        </div>
        <div>
          <label class="field-label">E-mail</label>
          <input v-model="dataForm.email" type="email" class="field-input" placeholder="email@empresa.com" />
          <p v-if="dataErrors.email" class="mt-1 text-xs text-red-400">{{ dataErrors.email }}</p>
        </div>
        <div>
          <label class="field-label">Telefone</label>
          <input v-model="dataForm.phone" type="tel" class="field-input" placeholder="(11) 99999-9999" />
          <p v-if="dataErrors.phone" class="mt-1 text-xs text-red-400">{{ dataErrors.phone }}</p>
        </div>

        <div class="modal-footer">
          <button class="btn-ghost" @click="emit('close')">Cancelar</button>
          <button :disabled="dataSaving" class="btn-primary" @click="saveData">
            {{ dataSaving ? 'Salvando…' : 'Salvar dados' }}
          </button>
        </div>
      </div>

      <!-- Tab: Redefinir senha -->
      <div v-else class="modal-body space-y-4">
        <p class="text-sm" style="color: var(--c-text-muted)">
          Defina uma nova senha para <strong style="color: var(--c-text)">{{ member.user_name }}</strong>.
          Você precisará confirmar com a sua própria senha de administrador.
        </p>

        <div v-if="pwSuccess" class="alert-success">{{ pwSuccess }}</div>
        <div v-if="pwErrors.general" class="alert-error">{{ pwErrors.general }}</div>

        <div>
          <label class="field-label">Nova senha</label>
          <input v-model="pwForm.password" type="password" class="field-input" placeholder="••••••••" />
          <p v-if="pwErrors.password" class="mt-1 text-xs text-red-400">{{ pwErrors.password }}</p>
        </div>
        <div>
          <label class="field-label">Confirmar nova senha</label>
          <input v-model="pwForm.password_confirmation" type="password" class="field-input" placeholder="••••••••" />
          <p v-if="pwErrors.password_confirmation" class="mt-1 text-xs text-red-400">{{ pwErrors.password_confirmation }}</p>
        </div>
        <div class="border-t pt-4" style="border-color: var(--c-border)">
          <label class="field-label">Sua senha (confirmação)</label>
          <input v-model="pwForm.admin_password" type="password" class="field-input" placeholder="••••••••" @keyup.enter="savePassword" />
          <p v-if="pwErrors.admin_password" class="mt-1 text-xs text-red-400">{{ pwErrors.admin_password }}</p>
        </div>

        <div class="modal-footer">
          <button class="btn-ghost" @click="emit('close')">Cancelar</button>
          <button
            :disabled="pwSaving || !pwForm.admin_password || !pwForm.password"
            class="btn-primary"
            @click="savePassword"
          >
            {{ pwSaving ? 'Redefinindo…' : 'Redefinir senha' }}
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
@reference "../style.css";

.modal-backdrop {
  @apply fixed inset-0 z-50 flex items-center justify-center p-4;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
}

.modal-panel {
  @apply w-full max-w-md rounded-2xl shadow-2xl overflow-hidden;
  background: var(--c-sidebar);
  border: 1px solid var(--c-border);
}

.modal-header {
  @apply flex items-center gap-3 px-5 py-4 border-b;
  border-color: var(--c-border);
}

.modal-close {
  @apply flex h-7 w-7 items-center justify-center rounded-lg transition-colors;
  color: var(--c-text-muted);
}

.modal-close:hover {
  background: var(--c-nav-hover);
  color: var(--c-text);
}

.modal-tabs {
  @apply flex border-b;
  border-color: var(--c-border);
}

.tab-btn {
  @apply flex-1 px-4 py-2.5 text-sm font-medium transition-colors;
  color: var(--c-text-muted);
}

.tab-btn:hover {
  color: var(--c-text);
}

.tab-active {
  color: #fbbf24 !important;
  border-bottom: 2px solid #fbbf24;
}

.modal-body {
  @apply px-5 py-5;
}

.modal-footer {
  @apply flex justify-end gap-2 pt-2;
}

.field-label {
  @apply mb-1.5 block text-sm;
  color: var(--c-text-muted);
}

.field-input {
  @apply w-full rounded-xl border px-4 py-2.5 text-sm outline-none transition;
  color: var(--c-text);
  background: var(--c-input-bg);
  border-color: var(--c-input-border);
}

.field-input:focus {
  border-color: rgba(251, 146, 60, 0.5);
  box-shadow: 0 0 0 2px rgba(251, 146, 60, 0.08);
}

.field-input::placeholder { color: var(--c-text-dim); }

.alert-success {
  @apply rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400;
}

.alert-error {
  @apply rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400;
}

.btn-primary {
  @apply rounded-xl px-4 py-2 text-sm font-semibold transition disabled:opacity-50;
  background: #f59e0b;
  color: #1c1917;
}

.btn-primary:hover:not(:disabled) { background: #fbbf24; }

.btn-ghost {
  @apply rounded-xl border px-4 py-2 text-sm font-medium transition;
  border-color: var(--c-border);
  color: var(--c-text-muted);
}

.btn-ghost:hover {
  color: var(--c-text);
  border-color: var(--c-border-strong);
}
</style>
