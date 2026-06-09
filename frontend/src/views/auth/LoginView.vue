<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import {
  lookupPasswordlessCompanies,
  requestPasswordlessCode,
  requestMagicLink,
  type PasswordlessCompany,
} from '../../api/auth'
import { ApiError } from '../../api/client'

const auth = useAuthStore()
const router = useRouter()

type Method = 'password' | 'code' | 'magic'
type Step = 'email' | 'company_select' | 'code_entry' | 'link_sent'

const method = ref<Method>('password')
const step = ref<Step>('email')

const email = ref('')
const password = ref('')
const codeInput = ref('')
const error = ref('')
const submitting = ref(false)

const foundCompanies = ref<PasswordlessCompany[]>([])
const selectedCompany = ref<PasswordlessCompany | null>(null)

function switchMethod(m: Method) {
  method.value = m
  step.value = 'email'
  error.value = ''
  foundCompanies.value = []
  selectedCompany.value = null
  codeInput.value = ''
}

// ─── Senha ────────────────────────────────────────────────
async function submitPassword() {
  error.value = ''
  try {
    const result = await auth.login(email.value, password.value)
    router.push({ name: result === 'select_company' ? 'select-company' : 'dashboard' })
  } catch (e) {
    error.value = e instanceof ApiError && (e.status === 401 || e.status === 422)
      ? 'E-mail ou senha inválidos.'
      : 'Erro ao conectar com o servidor.'
  }
}

// ─── Passwordless: lookup e seleção de empresa ─────────────
async function lookupAndProceed() {
  error.value = ''
  submitting.value = true
  try {
    const res = await lookupPasswordlessCompanies(email.value)
    const flag = method.value === 'code' ? 'passwordless_code_enabled' : 'magic_link_enabled'
    const eligible = res.companies.filter(c => c[flag])

    if (eligible.length === 0) {
      const featureName = method.value === 'code' ? 'Login por código' : 'Magic link'
      error.value = res.companies.length === 0
        ? 'Nenhuma conta encontrada para este e-mail.'
        : `${featureName} não está habilitado para ${res.companies.length === 1 ? 'esta empresa' : 'nenhuma das suas empresas'}.`
      return
    }

    if (eligible.length === 1) {
      await requestForCompany(eligible[0])
    } else {
      foundCompanies.value = eligible
      step.value = 'company_select'
    }
  } catch {
    error.value = 'Erro ao conectar com o servidor.'
  } finally {
    submitting.value = false
  }
}

async function requestForCompany(company: PasswordlessCompany) {
  selectedCompany.value = company
  error.value = ''
  submitting.value = true
  try {
    if (method.value === 'code') {
      await requestPasswordlessCode(email.value, company.uuid)
      codeInput.value = ''
      step.value = 'code_entry'
    } else {
      await requestMagicLink(email.value, company.uuid)
      step.value = 'link_sent'
    }
  } catch (e) {
    error.value = e instanceof ApiError
      ? ((e.data as { message?: string }).message ?? 'Erro ao enviar.')
      : 'Erro ao conectar com o servidor.'
    step.value = 'email'
  } finally {
    submitting.value = false
  }
}

// ─── Código OTP: verificar ─────────────────────────────────
async function verifyCode() {
  error.value = ''
  try {
    await auth.loginWithCode(email.value, selectedCompany.value!.uuid, codeInput.value.trim())
    router.push({ name: 'dashboard' })
  } catch (e) {
    error.value = e instanceof ApiError && e.status === 422
      ? 'Código inválido, expirado ou já utilizado.'
      : 'Erro ao verificar código.'
  }
}

function backToEmail() {
  step.value = 'email'
  error.value = ''
  foundCompanies.value = []
  selectedCompany.value = null
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-sm">
      <div class="mb-8 text-center">
        <span class="text-3xl font-bold text-amber-400">TechFix</span>
        <p class="mt-1 text-sm text-stone-400">Sistema de Ordens de Serviço</p>
      </div>

      <div class="rounded-2xl border border-white/10 bg-black/30 p-8 shadow-2xl backdrop-blur">
        <h1 class="mb-6 text-xl font-semibold text-white">Entrar</h1>

        <!-- Seletor de método -->
        <div class="mb-6 flex rounded-xl border border-white/10 bg-white/5 p-1">
          <button
            v-for="(label, key) in { password: 'Senha', code: 'Código', magic: 'Magic Link' }"
            :key="key"
            type="button"
            :class="[
              'flex-1 rounded-lg px-3 py-1.5 text-xs font-medium transition',
              method === key ? 'bg-amber-500 text-stone-950' : 'text-stone-400 hover:text-stone-200',
            ]"
            @click="switchMethod(key as Method)"
          >
            {{ label }}
          </button>
        </div>

        <div v-if="error" class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
          {{ error }}
        </div>

        <!-- ═══ SENHA ═══ -->
        <form v-if="method === 'password'" class="space-y-4" @submit.prevent="submitPassword">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-stone-300">E-mail</label>
            <input v-model="email" type="email" required autocomplete="email" class="input" placeholder="seu@email.com" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-stone-300">Senha</label>
            <input v-model="password" type="password" required autocomplete="current-password" class="input" placeholder="••••••••" />
          </div>
          <button
            type="submit"
            :disabled="auth.loading"
            class="mt-2 w-full rounded-xl bg-amber-500 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
          >
            {{ auth.loading ? 'Entrando…' : 'Entrar' }}
          </button>
        </form>

        <!-- ═══ CÓDIGO OTP e MAGIC LINK (fluxo compartilhado) ═══ -->
        <template v-else>
          <!-- Passo 1: e-mail -->
          <form v-if="step === 'email'" class="space-y-4" @submit.prevent="lookupAndProceed">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-stone-300">E-mail</label>
              <input v-model="email" type="email" required autocomplete="email" class="input" placeholder="seu@email.com" />
            </div>
            <button
              type="submit"
              :disabled="submitting"
              class="mt-2 w-full rounded-xl bg-amber-500 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
            >
              {{ submitting ? 'Buscando…' : (method === 'code' ? 'Enviar Código' : 'Enviar Link de Acesso') }}
            </button>
          </form>

          <!-- Passo 2 (se múltiplas empresas): seleção de empresa -->
          <div v-else-if="step === 'company_select'" class="space-y-3">
            <p class="text-sm text-stone-300">Selecione a empresa:</p>
            <button
              v-for="company in foundCompanies"
              :key="company.uuid"
              type="button"
              :disabled="submitting"
              class="w-full rounded-xl border border-white/10 px-4 py-3 text-left text-sm text-stone-200 transition hover:border-amber-500/30 hover:bg-amber-500/5 disabled:opacity-50"
              @click="requestForCompany(company)"
            >
              {{ company.name }}
            </button>
            <button type="button" class="w-full text-center text-xs text-stone-500 hover:text-stone-300" @click="backToEmail">
              ← Voltar
            </button>
          </div>

          <!-- Passo 3 (código): digitar OTP -->
          <form v-else-if="step === 'code_entry'" class="space-y-4" @submit.prevent="verifyCode">
            <div class="rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3 text-sm text-amber-300">
              Código enviado para <strong>{{ email }}</strong>
              <span v-if="selectedCompany"> · {{ selectedCompany.name }}</span>.
              Válido por 10 minutos.
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-stone-300">Código de 6 dígitos</label>
              <input
                v-model="codeInput"
                type="text"
                inputmode="numeric"
                maxlength="6"
                pattern="[0-9]{6}"
                required
                autocomplete="one-time-code"
                class="input text-center font-mono text-2xl tracking-[0.5em]"
                placeholder="000000"
              />
            </div>
            <button
              type="submit"
              :disabled="auth.loading || codeInput.length < 6"
              class="mt-2 w-full rounded-xl bg-amber-500 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
            >
              {{ auth.loading ? 'Verificando…' : 'Confirmar Código' }}
            </button>
            <button type="button" class="w-full text-center text-xs text-stone-500 hover:text-stone-300" @click="backToEmail">
              ← Reenviar código
            </button>
          </form>

          <!-- Passo 3 (magic link): link enviado -->
          <div v-else-if="step === 'link_sent'" class="space-y-4 text-center">
            <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-500/10 text-3xl">📧</div>
            <div>
              <p class="text-base font-semibold text-white">Verifique seu e-mail</p>
              <p class="mt-1 text-sm text-stone-400">
                Enviamos um link de acesso para <strong class="text-stone-200">{{ email }}</strong>.<br>
                O link expira em 10 minutos.
              </p>
            </div>
            <button type="button" class="w-full text-center text-xs text-stone-500 hover:text-stone-300" @click="backToEmail">
              ← Enviar novamente
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.input {
  @apply w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/30;
}
</style>
