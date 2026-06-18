<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError } from '../../api/client'
import { useAuthStore } from '../../stores/auth'
import { useTenantRouting } from '../../composables/useTenantRouting'

const auth = useAuthStore()
const router = useRouter()
const { tenantRoute } = useTenantRouting()

const form = reactive({
  company_name: '',
  user_name: '',
  user_email: '',
  password: '',
  password_confirmation: '',
})

const submitting = ref(false)
const error = ref('')

const fieldErrors = computed(() => {
  const errors: Record<string, string> = {}

  if (form.company_name.trim().length === 0) {
    errors.company_name = 'Informe o nome da assistência.'
  }

  if (form.user_name.trim().length === 0) {
    errors.user_name = 'Informe o seu nome.'
  }

  if (form.user_email.trim().length === 0) {
    errors.user_email = 'Informe o e-mail.'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.user_email)) {
    errors.user_email = 'Informe um e-mail válido.'
  }

  if (form.password.length === 0) {
    errors.password = 'Informe uma senha.'
  } else if (form.password.length < 8) {
    errors.password = 'A senha deve ter pelo menos 8 caracteres.'
  }

  if (form.password_confirmation.length === 0) {
    errors.password_confirmation = 'Confirme a senha.'
  } else if (form.password_confirmation !== form.password) {
    errors.password_confirmation = 'A confirmação deve ser igual à senha.'
  }

  return errors
})

const canSubmit = computed(() => Object.keys(fieldErrors.value).length === 0)

async function submit() {
  error.value = ''

  if (!canSubmit.value) {
    return
  }

  submitting.value = true

  try {
    await auth.register({
      company_name: form.company_name.trim(),
      user_name: form.user_name.trim(),
      user_email: form.user_email.trim().toLowerCase(),
      password: form.password,
      password_confirmation: form.password_confirmation,
    })

    router.push(tenantRoute('dashboard'))
  } catch (e) {
    if (e instanceof ApiError) {
      const data = e.data as { message?: string; errors?: Record<string, string[]> }

      if (e.status === 422 && data.errors) {
        error.value = Object.values(data.errors).flat()[0] ?? 'Verifique os campos informados.'
      } else if (e.status === 429) {
        error.value = 'Muitas tentativas de cadastro. Tente novamente mais tarde.'
      } else {
        error.value = data.message ?? 'Não foi possível concluir o cadastro.'
      }
    } else {
      error.value = 'Não foi possível concluir o cadastro.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-lg">
      <div class="mb-8 text-center">
        <span class="text-3xl font-bold text-amber-400">TechFix</span>
        <p class="mt-1 text-sm text-stone-400">Crie a primeira conta da sua assistência</p>
      </div>

      <div class="rounded-2xl border border-white/10 bg-black/30 p-8 shadow-2xl backdrop-blur">
        <h1 class="mb-2 text-xl font-semibold text-white">Criar conta</h1>
        <p class="mb-6 text-sm text-stone-400">
          Cadastre sua empresa e entre imediatamente como administrador.
        </p>

        <div v-if="error" class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
          {{ error }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-stone-300">Nome da assistência</label>
            <input v-model="form.company_name" type="text" autocomplete="organization" class="input" placeholder="Ex.: Central do Notebook" />
            <p v-if="fieldErrors.company_name" class="mt-1 text-xs text-red-300">{{ fieldErrors.company_name }}</p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-stone-300">Seu nome</label>
            <input v-model="form.user_name" type="text" autocomplete="name" class="input" placeholder="Ex.: Thiago Silva" />
            <p v-if="fieldErrors.user_name" class="mt-1 text-xs text-red-300">{{ fieldErrors.user_name }}</p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-stone-300">E-mail</label>
            <input v-model="form.user_email" type="email" autocomplete="email" class="input" placeholder="seu@email.com" />
            <p v-if="fieldErrors.user_email" class="mt-1 text-xs text-red-300">{{ fieldErrors.user_email }}</p>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-stone-300">Senha</label>
              <input v-model="form.password" type="password" autocomplete="new-password" class="input" placeholder="Mínimo de 8 caracteres" />
              <p v-if="fieldErrors.password" class="mt-1 text-xs text-red-300">{{ fieldErrors.password }}</p>
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-medium text-stone-300">Confirmar senha</label>
              <input v-model="form.password_confirmation" type="password" autocomplete="new-password" class="input" placeholder="Repita a senha" />
              <p v-if="fieldErrors.password_confirmation" class="mt-1 text-xs text-red-300">{{ fieldErrors.password_confirmation }}</p>
            </div>
          </div>

          <button
            type="submit"
            :disabled="submitting || !canSubmit"
            class="mt-2 w-full rounded-xl bg-amber-500 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
          >
            {{ submitting ? 'Criando conta…' : 'Criar conta' }}
          </button>
        </form>

        <p class="mt-6 text-center text-sm text-stone-400">
          Já tem acesso?
          <RouterLink :to="{ name: 'login' }" class="font-medium text-amber-300 hover:text-amber-200">
            Entrar
          </RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>
