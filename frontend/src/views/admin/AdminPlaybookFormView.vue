<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  adminGetPlaybook,
  adminCreatePlaybook,
  adminUpdatePlaybook,
} from '../../api/adminPlaybooks'
import type { PlaybookPayload, PlaybookStepPayload } from '../../api/playbooks'

const route = useRoute()
const router = useRouter()

const isEdit = computed(() => route.name === 'admin.playbooks.edit')
const playbookId = computed(() => (isEdit.value ? Number(route.params.id) : null))

const loading = ref(false)
const saving = ref(false)
const errors = ref<Record<string, string>>({})

const title = ref('')
const description = ref('')
const category = ref('')
const emoji = ref('📖')
const status = ref<'draft' | 'published'>('draft')

interface StepForm extends PlaybookStepPayload {
  _key: number
  showTip: boolean
}

let nextKey = 1
function makeStep(): StepForm {
  return { _key: nextKey++, title: '', content: '', tip: null, tip_type: 'info', showTip: false }
}

const steps = ref<StepForm[]>([makeStep()])

const SUGGESTED_CATEGORIES = [
  'Atendimento', 'Diagnóstico', 'Execução', 'Financeiro',
  'Entrega', 'Garantia', 'Segurança', 'Treinamento',
]

function addStep() {
  steps.value.push(makeStep())
  setTimeout(() => {
    const els = document.querySelectorAll<HTMLElement>('.step-title-input')
    els[els.length - 1]?.focus()
  }, 50)
}

function removeStep(i: number) {
  if (steps.value.length === 1) return
  steps.value.splice(i, 1)
}

function moveUp(i: number) {
  if (i === 0) return
  ;[steps.value[i - 1], steps.value[i]] = [steps.value[i], steps.value[i - 1]]
}

function moveDown(i: number) {
  if (i === steps.value.length - 1) return
  ;[steps.value[i], steps.value[i + 1]] = [steps.value[i + 1], steps.value[i]]
}

function toggleTip(step: StepForm) {
  step.showTip = !step.showTip
  if (!step.showTip) step.tip = null
}

onMounted(async () => {
  if (!isEdit.value || !playbookId.value) return
  loading.value = true
  try {
    const res = await adminGetPlaybook(playbookId.value)
    const pb = res.data
    title.value = pb.title
    description.value = pb.description ?? ''
    category.value = pb.category ?? ''
    emoji.value = pb.emoji ?? '📖'
    status.value = pb.status
    steps.value = pb.steps.map((s) => ({
      _key: nextKey++,
      title: s.title,
      content: s.content,
      tip: s.tip,
      tip_type: s.tip_type,
      showTip: !!s.tip,
    }))
  } finally {
    loading.value = false
  }
})

function validate(): boolean {
  errors.value = {}
  if (!title.value.trim()) errors.value.title = 'O título é obrigatório.'
  steps.value.forEach((s, i) => {
    if (!s.title.trim()) errors.value[`step_${i}_title`] = 'Título obrigatório.'
    if (!s.content.trim()) errors.value[`step_${i}_content`] = 'Conteúdo obrigatório.'
  })
  return Object.keys(errors.value).length === 0
}

async function save() {
  if (!validate()) return
  saving.value = true
  try {
    const payload: PlaybookPayload = {
      title: title.value.trim(),
      description: description.value.trim() || null,
      category: category.value.trim() || null,
      emoji: emoji.value.trim() || '📖',
      status: status.value,
      steps: steps.value.map((s) => ({
        title: s.title.trim(),
        content: s.content.trim(),
        tip: s.tip ? s.tip.trim() : null,
        tip_type: s.tip_type,
      })),
    }

    if (isEdit.value && playbookId.value) {
      await adminUpdatePlaybook(playbookId.value, payload)
    } else {
      await adminCreatePlaybook(payload)
    }
    await router.push({ name: 'admin.playbooks' })
  } catch (err: unknown) {
    const e = err as { data?: { errors?: Record<string, string[]> } }
    if (e?.data?.errors) {
      for (const [key, msgs] of Object.entries(e.data.errors)) {
        errors.value[key] = msgs[0] ?? ''
      }
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <!-- Header -->
    <div class="mb-6 flex items-center gap-3">
      <RouterLink
        :to="{ name: 'admin.playbooks' }"
        class="text-sm text-stone-400 transition hover:text-white"
      >
        ← Playbooks
      </RouterLink>
      <span class="text-stone-600">/</span>
      <h1 class="text-lg font-semibold text-white">
        {{ isEdit ? 'Editar Playbook' : 'Novo Playbook' }}
      </h1>
    </div>

    <div v-if="loading" class="space-y-4">
      <div v-for="i in 3" :key="i" class="h-16 animate-pulse rounded-2xl bg-stone-800" />
    </div>

    <form v-else @submit.prevent="save" class="space-y-6">
      <!-- Informações gerais -->
      <div class="rounded-2xl border border-stone-700 bg-stone-800/50 p-5 space-y-4">
        <h2 class="text-sm font-semibold text-white">Informações gerais</h2>

        <!-- Emoji + Título -->
        <div class="flex gap-3">
          <div>
            <label class="form-label">Emoji</label>
            <input
              v-model="emoji"
              type="text"
              maxlength="4"
              class="w-14 h-11 rounded-xl border border-stone-700 bg-stone-900 text-center text-2xl text-white outline-none focus:border-amber-500"
            />
          </div>
          <div class="flex-1">
            <label class="form-label">Título <span class="text-red-400">*</span></label>
            <input
              v-model="title"
              type="text"
              class="admin-input w-full"
              :class="{ 'border-red-500': errors.title }"
              placeholder="Ex.: Como diagnosticar iPhone com tela preta"
              maxlength="255"
            />
            <p v-if="errors.title" class="mt-1 text-xs text-red-400">{{ errors.title }}</p>
          </div>
        </div>

        <div>
          <label class="form-label">Descrição curta <span class="text-stone-500 text-xs">(opcional)</span></label>
          <textarea
            v-model="description"
            class="admin-input w-full resize-none"
            rows="2"
            placeholder="Objetivo deste playbook em uma ou duas frases..."
            maxlength="500"
          />
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <label class="form-label">Categoria <span class="text-stone-500 text-xs">(opcional)</span></label>
            <input
              v-model="category"
              type="text"
              class="admin-input w-full"
              list="cat-list"
              placeholder="Ex.: Diagnóstico"
              maxlength="100"
            />
            <datalist id="cat-list">
              <option v-for="cat in SUGGESTED_CATEGORIES" :key="cat" :value="cat" />
            </datalist>
          </div>
          <div>
            <label class="form-label">Status</label>
            <div class="flex gap-2">
              <button
                type="button"
                class="flex-1 flex items-center justify-center gap-2 rounded-xl border py-2 text-xs font-medium transition"
                :class="status === 'draft' ? 'border-amber-500 bg-amber-500/10 text-amber-300' : 'border-stone-700 text-stone-400 hover:border-stone-500'"
                @click="status = 'draft'"
              >
                <span class="h-2 w-2 rounded-full bg-stone-500" />
                Rascunho
              </button>
              <button
                type="button"
                class="flex-1 flex items-center justify-center gap-2 rounded-xl border py-2 text-xs font-medium transition"
                :class="status === 'published' ? 'border-emerald-500 bg-emerald-500/10 text-emerald-300' : 'border-stone-700 text-stone-400 hover:border-stone-500'"
                @click="status = 'published'"
              >
                <span class="h-2 w-2 rounded-full bg-emerald-400" />
                Publicado
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Passos -->
      <div class="rounded-2xl border border-stone-700 bg-stone-800/50 p-5">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-white">
            Passos
            <span class="ml-1.5 rounded-full bg-stone-700 px-2 py-0.5 text-[11px] text-stone-400">{{ steps.length }}</span>
          </h2>
        </div>

        <div class="space-y-4">
          <div
            v-for="(step, i) in steps"
            :key="step._key"
            class="rounded-xl border border-stone-700 bg-stone-900/60 p-4"
          >
            <!-- Header do step -->
            <div class="flex items-center gap-3 border-b border-stone-700/60 pb-3">
              <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-xs font-bold text-amber-400">
                {{ i + 1 }}
              </div>
              <input
                v-model="step.title"
                type="text"
                class="step-title-input flex-1 bg-transparent text-sm font-medium text-white outline-none placeholder:text-stone-600"
                :class="{ 'text-red-400': errors[`step_${i}_title`] }"
                :placeholder="`Título do passo ${i + 1}...`"
                maxlength="255"
              />
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  class="icon-btn"
                  :disabled="i === 0"
                  @click="moveUp(i)"
                  title="Mover para cima"
                >↑</button>
                <button
                  type="button"
                  class="icon-btn"
                  :disabled="i === steps.length - 1"
                  @click="moveDown(i)"
                  title="Mover para baixo"
                >↓</button>
                <button
                  v-if="steps.length > 1"
                  type="button"
                  class="icon-btn hover:!text-red-400"
                  @click="removeStep(i)"
                  title="Remover"
                >✕</button>
              </div>
            </div>
            <p v-if="errors[`step_${i}_title`]" class="mt-1 text-xs text-red-400">{{ errors[`step_${i}_title`] }}</p>

            <!-- Conteúdo -->
            <div class="pt-3">
              <label class="form-label text-xs">Conteúdo / instruções *</label>
              <textarea
                v-model="step.content"
                class="admin-input w-full resize-none text-sm"
                :class="{ 'border-red-500': errors[`step_${i}_content`] }"
                rows="4"
                placeholder="Descreva o que deve ser feito neste passo. Use linhas separadas para etapas distintas."
              />
              <p v-if="errors[`step_${i}_content`]" class="mt-1 text-xs text-red-400">{{ errors[`step_${i}_content`] }}</p>
            </div>

            <!-- Tip toggle -->
            <div class="pt-2">
              <button
                type="button"
                class="text-xs transition"
                :class="step.showTip ? 'text-amber-400' : 'text-stone-500 hover:text-stone-300'"
                @click="toggleTip(step)"
              >
                {{ step.showTip ? '− Remover dica/aviso' : '+ Adicionar dica ou aviso' }}
              </button>

              <div v-if="step.showTip" class="mt-3 space-y-2 rounded-xl border border-stone-700 bg-stone-800 p-3">
                <div class="flex gap-2">
                  <button
                    v-for="type in (['info', 'warning', 'success'] as const)"
                    :key="type"
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-medium transition"
                    :class="step.tip_type === type
                      ? 'border-amber-500/40 bg-amber-500/10 text-amber-300'
                      : 'border-stone-700 text-stone-400 hover:border-stone-500'"
                    @click="step.tip_type = type"
                  >
                    <span
                      class="h-2 w-2 rounded-full"
                      :style="{ background: type === 'info' ? '#60a5fa' : type === 'warning' ? '#f59e0b' : '#34d399' }"
                    />
                    {{ type === 'info' ? 'Informação' : type === 'warning' ? 'Atenção' : 'Dica' }}
                  </button>
                </div>
                <textarea
                  v-model="step.tip"
                  class="admin-input w-full resize-none text-sm"
                  rows="2"
                  placeholder="Ex.: Certifique-se de desligar o equipamento antes de prosseguir..."
                  maxlength="500"
                />
              </div>
            </div>
          </div>
        </div>

        <button
          type="button"
          class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-stone-700 py-3 text-sm text-stone-500 transition hover:border-amber-500/50 hover:text-amber-400"
          @click="addStep"
        >
          + Adicionar passo
        </button>
      </div>

      <!-- Ações -->
      <div class="flex items-center justify-between">
        <RouterLink
          :to="{ name: 'admin.playbooks' }"
          class="rounded-xl border border-stone-700 px-4 py-2 text-sm text-stone-400 transition hover:text-white"
        >
          Cancelar
        </RouterLink>
        <button
          type="submit"
          :disabled="saving"
          class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-5 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-60"
        >
          {{ saving ? 'Salvando...' : isEdit ? 'Salvar alterações' : 'Criar Playbook' }}
        </button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.form-label {
  display: block;
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--c-text-muted);
  margin-bottom: 0.375rem;
}

.admin-input {
  border-radius: 0.75rem;
  border: 1px solid var(--c-input-border);
  background: var(--c-input-bg);
  color: var(--c-text);
  padding: 0.625rem 0.75rem;
  font-size: 0.875rem;
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s, background-color 0.15s;
}
.admin-input:focus {
  border-color: #f59e0b;
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.12);
}

.icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 0.5rem;
  font-size: 0.75rem;
  color: var(--c-text-muted);
  transition: background 0.15s, color 0.15s;
}
.icon-btn:hover:not(:disabled) {
  background: var(--c-nav-hover);
  color: var(--c-text);
}
.icon-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

:deep(.step-title-input::placeholder) {
  color: var(--c-text-dim);
}
</style>
