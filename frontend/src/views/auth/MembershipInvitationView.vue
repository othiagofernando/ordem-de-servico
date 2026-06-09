<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { acceptInvitation, fetchInvitation, type MembershipInvitation } from '../../api/auth'
import { ApiError } from '../../api/client'

const route = useRoute()
const router = useRouter()
const token = route.params.token as string

const loading = ref(true)
const accepting = ref(false)
const error = ref('')
const invitation = ref<MembershipInvitation | null>(null)
const form = ref({
  name: '',
  password: '',
  password_confirmation: '',
})

onMounted(async () => {
  try {
    const res = await fetchInvitation(token)
    invitation.value = res.data
    form.value.name = res.data.user.name ?? ''
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Erro ao carregar convite.'
  } finally {
    loading.value = false
  }
})

async function submit() {
  if (!invitation.value) return

  error.value = ''
  accepting.value = true

  try {
    const payload = invitation.value.requires_password_setup
      ? {
          name: form.value.name,
          password: form.value.password,
          password_confirmation: form.value.password_confirmation,
        }
      : undefined

    const res = await acceptInvitation(token, payload)
    localStorage.setItem('access_token', res.access_token)
    router.push({ name: 'dashboard' })
  } catch (e) {
    if (e instanceof ApiError) {
      error.value = (e.data as { message?: string })?.message ?? 'Erro ao aceitar convite.'
    } else {
      error.value = 'Erro ao aceitar convite.'
    }
  } finally {
    accepting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <span class="text-3xl font-bold text-amber-400">TechFix</span>
        <p class="mt-1 text-sm text-stone-400">Convite de acesso</p>
      </div>

      <div class="rounded-2xl border border-white/10 bg-black/30 p-8 shadow-2xl backdrop-blur">
        <div v-if="loading" class="py-12 text-center text-stone-500">Carregando convite…</div>

        <div v-else-if="error && !invitation" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
          {{ error }}
        </div>

        <template v-else-if="invitation">
          <h1 class="text-xl font-semibold text-white">Aceitar convite</h1>
          <p class="mt-2 text-sm text-stone-400">
            Você foi convidado para entrar em <strong class="text-stone-200">{{ invitation.company.name }}</strong>
            como <strong class="text-amber-300">{{ invitation.membership.role }}</strong>.
          </p>
          <p class="mt-2 text-sm text-stone-400">
            E-mail: <strong class="text-stone-200">{{ invitation.user.email }}</strong>
          </p>

          <div v-if="error" class="mt-4 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
            {{ error }}
          </div>

          <form class="mt-6 space-y-4" @submit.prevent="submit">
            <template v-if="invitation.requires_password_setup">
              <div>
                <label class="mb-1.5 block text-sm font-medium text-stone-300">Nome</label>
                <input v-model="form.name" type="text" required class="input" />
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium text-stone-300">Senha</label>
                <input v-model="form.password" type="password" minlength="8" required class="input" />
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium text-stone-300">Confirmar senha</label>
                <input v-model="form.password_confirmation" type="password" minlength="8" required class="input" />
              </div>
            </template>

            <button
              type="submit"
              :disabled="accepting"
              class="w-full rounded-xl bg-amber-500 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
            >
              {{ accepting ? 'Aceitando…' : 'Aceitar convite' }}
            </button>
          </form>
        </template>
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
