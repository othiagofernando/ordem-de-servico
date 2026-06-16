<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { changePassword } from '../../api/account'
import { ApiError } from '../../api/client'

const auth = useAuthStore()

const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const saving = ref(false)
const successMsg = ref('')
const errors = ref<Record<string, string>>({})

async function submitPasswordChange() {
  errors.value = {}
  successMsg.value = ''

  if (newPassword.value !== confirmPassword.value) {
    errors.value.confirm = 'As senhas não coincidem.'
    return
  }
  if (newPassword.value.length < 8) {
    errors.value.password = 'A nova senha deve ter ao menos 8 caracteres.'
    return
  }

  saving.value = true
  try {
    const res = await changePassword(currentPassword.value, newPassword.value, confirmPassword.value)
    successMsg.value = res.message
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
  } catch (e) {
    if (e instanceof ApiError) {
      const data = e.data as { errors?: Record<string, string[]>; message?: string }
      if (data.errors) {
        for (const [k, v] of Object.entries(data.errors)) {
          errors.value[k] = v[0]
        }
      } else {
        errors.value.general = data.message ?? 'Erro ao alterar senha.'
      }
    } else {
      errors.value.general = 'Erro inesperado.'
    }
  } finally {
    saving.value = false
  }
}

const roleLabels: Record<string, string> = {
  administrator: 'Administrador',
  manager: 'Gerente',
  attendant: 'Atendente',
  technician: 'Técnico',
  finance: 'Financeiro',
}
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-8">
    <div>
      <h1 class="text-2xl font-semibold" style="color: var(--c-text)">Minha Conta</h1>
      <p class="mt-1 text-sm" style="color: var(--c-text-muted)">Informações do seu perfil e configurações de segurança.</p>
    </div>

    <!-- Informações do perfil -->
    <section class="card space-y-4">
      <h2 class="text-sm font-semibold uppercase tracking-widest" style="color: var(--c-text-muted)">Perfil</h2>
      <div class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
        <div>
          <p style="color: var(--c-text-muted)">Nome</p>
          <p class="mt-0.5 font-medium" style="color: var(--c-text)">{{ auth.user?.name ?? '—' }}</p>
        </div>
        <div>
          <p style="color: var(--c-text-muted)">E-mail</p>
          <p class="mt-0.5 font-medium" style="color: var(--c-text)">{{ auth.user?.email ?? '—' }}</p>
        </div>
        <div>
          <p style="color: var(--c-text-muted)">Empresa</p>
          <p class="mt-0.5 font-medium" style="color: var(--c-text)">{{ auth.company?.name ?? '—' }}</p>
        </div>
        <div>
          <p style="color: var(--c-text-muted)">Perfil de acesso</p>
          <p class="mt-0.5 font-medium" style="color: var(--c-text)">{{ roleLabels[auth.role ?? ''] ?? auth.role ?? '—' }}</p>
        </div>
      </div>
    </section>

    <!-- Alterar senha -->
    <section class="card space-y-5">
      <div>
        <h2 class="text-sm font-semibold uppercase tracking-widest" style="color: var(--c-text-muted)">Alterar senha</h2>
        <p class="mt-1 text-xs" style="color: var(--c-text-dim)">Mínimo de 8 caracteres. Você precisará da sua senha atual para confirmar.</p>
      </div>

      <div v-if="successMsg" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
        {{ successMsg }}
      </div>
      <div v-if="errors.general" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
        {{ errors.general }}
      </div>

      <div class="space-y-4">
        <div>
          <label class="field-label">Senha atual</label>
          <input v-model="currentPassword" type="password" class="field-input" placeholder="••••••••" />
          <p v-if="errors.current_password" class="mt-1 text-xs text-red-400">{{ errors.current_password }}</p>
        </div>
        <div>
          <label class="field-label">Nova senha</label>
          <input v-model="newPassword" type="password" class="field-input" placeholder="••••••••" />
          <p v-if="errors.password" class="mt-1 text-xs text-red-400">{{ errors.password }}</p>
        </div>
        <div>
          <label class="field-label">Confirmar nova senha</label>
          <input v-model="confirmPassword" type="password" class="field-input" placeholder="••••••••" @keyup.enter="submitPasswordChange" />
          <p v-if="errors.confirm" class="mt-1 text-xs text-red-400">{{ errors.confirm }}</p>
        </div>
      </div>

      <button
        :disabled="saving || !currentPassword || !newPassword || !confirmPassword"
        class="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
        @click="submitPasswordChange"
      >
        {{ saving ? 'Salvando…' : 'Alterar senha' }}
      </button>
    </section>

    <!-- Dica sobre novos usuários -->
    <section class="rounded-2xl border px-5 py-4 text-sm" style="border-color: var(--c-border); background: var(--c-surface); color: var(--c-text-muted)">
      <p class="font-medium" style="color: var(--c-text)">Como adicionar usuários ao sistema?</p>
      <p class="mt-1">Acesse <strong style="color: var(--c-text)">Usuários</strong> no menu lateral e use o botão <strong style="color: var(--c-text)">Convidar membro</strong> para enviar um convite por e-mail. O usuário convidado criará sua própria senha ao aceitar o convite.</p>
    </section>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.card {
  @apply rounded-2xl border p-6;
  background: var(--c-surface);
  border-color: var(--c-border);
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
  box-shadow: 0 0 0 3px rgba(251, 146, 60, 0.08);
}

.field-input::placeholder {
  color: var(--c-text-dim);
}
</style>
