<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTenantRouting } from '../../composables/useTenantRouting'
import {
  getPlaybook,
  createPlaybook,
  updatePlaybook,
  type PlaybookPayload,
  type PlaybookStepPayload,
} from '../../api/playbooks'

const route = useRoute()
const router = useRouter()
const { tenantRoute } = useTenantRouting()

const isEdit = computed(() => route.name === 'learning.edit')
const playBookId = computed(() => (isEdit.value ? Number(route.params.id) : null))

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
  'Atendimento',
  'Diagnóstico',
  'Execução',
  'Financeiro',
  'Entrega',
  'Garantia',
  'Segurança',
  'Treinamento',
]

function addStep() {
  steps.value.push(makeStep())
  setTimeout(() => {
    const els = document.querySelectorAll('.step-title-input')
    const last = els[els.length - 1] as HTMLElement | undefined
    last?.focus()
  }, 50)
}

function removeStep(index: number) {
  if (steps.value.length === 1) return
  steps.value.splice(index, 1)
}

function moveUp(index: number) {
  if (index === 0) return
  ;[steps.value[index - 1], steps.value[index]] = [steps.value[index], steps.value[index - 1]]
}

function moveDown(index: number) {
  if (index === steps.value.length - 1) return
  ;[steps.value[index], steps.value[index + 1]] = [steps.value[index + 1], steps.value[index]]
}

function toggleTip(step: StepForm) {
  step.showTip = !step.showTip
  if (!step.showTip) step.tip = null
}

onMounted(async () => {
  if (!isEdit.value || !playBookId.value) return
  loading.value = true
  try {
    const res = await getPlaybook(playBookId.value)
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
  for (let i = 0; i < steps.value.length; i++) {
    const s = steps.value[i]
    if (!s.title.trim()) errors.value[`step_${i}_title`] = 'Título obrigatório.'
    if (!s.content.trim()) errors.value[`step_${i}_content`] = 'Conteúdo obrigatório.'
  }
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

    if (isEdit.value && playBookId.value) {
      await updatePlaybook(playBookId.value, payload)
      await router.push(tenantRoute('learning.detail', { params: { id: playBookId.value } }))
    } else {
      const res = await createPlaybook(payload)
      await router.push(tenantRoute('learning.detail', { params: { id: res.data.id } }))
    }
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
    <!-- Cabeçalho -->
    <div class="mb-6 flex items-center gap-4">
      <RouterLink
        :to="tenantRoute('learning')"
        class="inline-flex items-center gap-1.5 text-sm transition-colors hover:underline"
        style="color: var(--c-text-muted)"
      >
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
        Aprendizado
      </RouterLink>
      <span style="color: var(--c-text-dim)">/</span>
      <h1 class="text-lg font-bold" style="color: var(--c-text)">
        {{ isEdit ? 'Editar Playbook' : 'Novo Playbook' }}
      </h1>
    </div>

    <div v-if="loading" class="space-y-4">
      <div class="h-10 rounded-xl card-skeleton" />
      <div class="h-20 rounded-xl card-skeleton" />
      <div class="h-48 rounded-xl card-skeleton" />
    </div>

    <form v-else @submit.prevent="save" class="space-y-6">
      <!-- Card: Informações gerais -->
      <div class="form-card">
        <h2 class="form-section-title">Informações gerais</h2>

        <!-- Emoji + Título -->
        <div class="flex gap-3">
          <div class="shrink-0">
            <label class="form-label">Emoji</label>
            <input
              v-model="emoji"
              type="text"
              maxlength="4"
              class="emoji-input"
              placeholder="📖"
            />
          </div>
          <div class="flex-1">
            <label class="form-label">Título <span class="text-red-400">*</span></label>
            <input
              v-model="title"
              type="text"
              class="form-input"
              :class="{ 'form-input-error': errors.title }"
              placeholder="Ex.: Como fazer diagnóstico de iPhone"
              maxlength="255"
            />
            <p v-if="errors.title" class="form-error">{{ errors.title }}</p>
          </div>
        </div>

        <!-- Descrição -->
        <div>
          <label class="form-label">Descrição curta <span class="text-xs" style="color: var(--c-text-dim)">(opcional)</span></label>
          <textarea
            v-model="description"
            class="form-input"
            rows="2"
            placeholder="Descreva brevemente o objetivo deste playbook..."
            maxlength="500"
          />
        </div>

        <!-- Categoria + Status -->
        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <label class="form-label">Categoria <span class="text-xs" style="color: var(--c-text-dim)">(opcional)</span></label>
            <input
              v-model="category"
              type="text"
              class="form-input"
              list="category-suggestions"
              placeholder="Ex.: Diagnóstico"
              maxlength="100"
            />
            <datalist id="category-suggestions">
              <option v-for="cat in SUGGESTED_CATEGORIES" :key="cat" :value="cat" />
            </datalist>
          </div>
          <div>
            <label class="form-label">Status</label>
            <div class="flex gap-2">
              <button
                type="button"
                class="status-btn flex-1"
                :class="{ 'status-btn-active': status === 'draft' }"
                @click="status = 'draft'"
              >
                <span class="h-2 w-2 rounded-full" style="background: var(--c-text-dim)" />
                Rascunho
              </button>
              <button
                type="button"
                class="status-btn flex-1"
                :class="{ 'status-btn-active': status === 'published' }"
                @click="status = 'published'"
              >
                <span class="h-2 w-2 rounded-full bg-emerald-400" />
                Publicado
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Card: Passos -->
      <div class="form-card">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="form-section-title mb-0">
            Passos
            <span class="ml-1.5 rounded-full px-2 py-0.5 text-[11px]" style="background: var(--c-surface-hover); color: var(--c-text-dim)">
              {{ steps.length }}
            </span>
          </h2>
          <p class="text-xs" style="color: var(--c-text-dim)">Arraste para reordenar</p>
        </div>

        <div class="space-y-4">
          <div
            v-for="(step, i) in steps"
            :key="step._key"
            class="step-card"
          >
            <!-- Header do step -->
            <div class="flex items-center gap-3 border-b pb-3" style="border-color: var(--c-border)">
              <div class="step-num-badge">{{ i + 1 }}</div>
              <div class="flex-1">
                <input
                  v-model="step.title"
                  type="text"
                  class="step-title-input w-full bg-transparent text-sm font-semibold outline-none"
                  :class="{ 'text-red-400': errors[`step_${i}_title`] }"
                  :placeholder="`Título do passo ${i + 1}...`"
                  maxlength="255"
                />
                <p v-if="errors[`step_${i}_title`]" class="form-error">{{ errors[`step_${i}_title`] }}</p>
              </div>
              <!-- Controles de ordem e remoção -->
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  class="icon-btn"
                  :disabled="i === 0"
                  title="Mover para cima"
                  @click="moveUp(i)"
                >
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75 12 8.25l7.5 7.5" />
                  </svg>
                </button>
                <button
                  type="button"
                  class="icon-btn"
                  :disabled="i === steps.length - 1"
                  title="Mover para baixo"
                  @click="moveDown(i)"
                >
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                  </svg>
                </button>
                <button
                  v-if="steps.length > 1"
                  type="button"
                  class="icon-btn hover:text-red-400"
                  title="Remover passo"
                  @click="removeStep(i)"
                >
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Conteúdo do step -->
            <div class="pt-3">
              <label class="form-label text-xs">Conteúdo / instruções <span class="text-red-400">*</span></label>
              <textarea
                v-model="step.content"
                class="form-input text-sm"
                :class="{ 'form-input-error': errors[`step_${i}_content`] }"
                rows="4"
                placeholder="Descreva detalhadamente o que deve ser feito neste passo. Use parágrafos para separar ideias."
              />
              <p v-if="errors[`step_${i}_content`]" class="form-error">{{ errors[`step_${i}_content`] }}</p>
            </div>

            <!-- Toggle de tip -->
            <div class="pt-2">
              <button
                type="button"
                class="flex items-center gap-2 text-xs transition-colors"
                :style="{ color: step.showTip ? '#fbbf24' : 'var(--c-text-dim)' }"
                @click="toggleTip(step)"
              >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path
                    v-if="step.showTip"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6 18 18 6M6 6l12 12"
                  />
                  <path
                    v-else
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                  />
                </svg>
                {{ step.showTip ? 'Remover dica/aviso' : 'Adicionar dica ou aviso' }}
              </button>

              <div v-if="step.showTip" class="mt-3 space-y-2 rounded-xl border p-3" style="border-color: var(--c-border); background: var(--c-surface-hover)">
                <div class="flex gap-2">
                  <button
                    v-for="type in (['info', 'warning', 'success'] as const)"
                    :key="type"
                    type="button"
                    class="tip-type-btn"
                    :class="{ 'tip-type-btn-active': step.tip_type === type }"
                    :data-type="type"
                    @click="step.tip_type = type"
                  >
                    <span
                      class="h-2 w-2 rounded-full"
                      :style="{
                        background: type === 'info' ? '#60a5fa' : type === 'warning' ? '#f59e0b' : '#34d399',
                      }"
                    />
                    {{ type === 'info' ? 'Informação' : type === 'warning' ? 'Atenção' : 'Dica' }}
                  </button>
                </div>
                <textarea
                  v-model="step.tip"
                  class="form-input text-sm"
                  rows="2"
                  placeholder="Ex.: Certifique-se de desligar o equipamento antes de abrir..."
                  maxlength="500"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Adicionar step -->
        <button
          type="button"
          class="add-step-btn mt-4 w-full"
          @click="addStep"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
          Adicionar passo
        </button>
      </div>

      <!-- Ações -->
      <div class="flex items-center justify-between">
        <RouterLink
          :to="isEdit && playBookId ? tenantRoute('learning.detail', { params: { id: playBookId } }) : tenantRoute('learning')"
          class="btn-cancel"
        >
          Cancelar
        </RouterLink>
        <button type="submit" class="btn-save" :disabled="saving">
          <svg v-if="saving" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
          {{ saving ? 'Salvando...' : isEdit ? 'Salvar alterações' : 'Criar Playbook' }}
        </button>
      </div>
    </form>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.card-skeleton {
  background: var(--c-surface);
  animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

/* Cards */
.form-card {
  @apply rounded-2xl border p-5 space-y-4;
  background: var(--c-surface);
  border-color: var(--c-border);
}

.form-section-title {
  @apply text-sm font-semibold mb-4;
  color: var(--c-text);
}

/* Labels e inputs */
.form-label {
  @apply block text-xs font-medium mb-1.5;
  color: var(--c-text-muted);
}

.form-input {
  @apply w-full rounded-xl border px-3 py-2.5 text-sm outline-none transition-colors resize-none;
  background: var(--c-surface-hover);
  border-color: var(--c-border);
  color: var(--c-text);
}
.form-input:focus {
  border-color: #fbbf24;
}
.form-input-error {
  border-color: #f87171 !important;
}

.form-error {
  @apply mt-1 text-xs text-red-400;
}

.emoji-input {
  @apply rounded-xl border px-3 text-center text-2xl outline-none transition-colors;
  width: 3.5rem;
  height: 2.75rem;
  background: var(--c-surface-hover);
  border-color: var(--c-border);
  color: var(--c-text);
}
.emoji-input:focus {
  border-color: #fbbf24;
}

/* Status toggle */
.status-btn {
  @apply flex items-center justify-center gap-2 rounded-xl border px-3 py-2 text-xs font-medium transition-colors;
  border-color: var(--c-border);
  color: var(--c-text-muted);
}
.status-btn-active {
  border-color: #fbbf24 !important;
  background: rgba(251, 191, 36, 0.1) !important;
  color: #fbbf24 !important;
}

/* Steps */
.step-card {
  @apply rounded-xl border p-4 space-y-0;
  background: var(--c-surface-hover);
  border-color: var(--c-border);
}

.step-num-badge {
  @apply flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold;
  background: rgba(251, 191, 36, 0.15);
  color: #fbbf24;
}

.step-title-input {
  color: var(--c-text);
}
.step-title-input::placeholder {
  color: var(--c-text-dim);
}

.icon-btn {
  @apply flex h-7 w-7 items-center justify-center rounded-lg transition-colors disabled:cursor-not-allowed disabled:opacity-30;
  color: var(--c-text-dim);
}
.icon-btn:not(:disabled):hover {
  background: var(--c-nav-hover);
  color: var(--c-text);
}

/* Tip type */
.tip-type-btn {
  @apply flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-medium transition-colors;
  border-color: var(--c-border);
  color: var(--c-text-muted);
}
.tip-type-btn-active {
  border-color: rgba(251, 191, 36, 0.4) !important;
  background: rgba(251, 191, 36, 0.08) !important;
  color: var(--c-text) !important;
}

/* Adicionar step */
.add-step-btn {
  @apply flex items-center justify-center gap-2 rounded-xl border border-dashed py-3 text-sm font-medium transition-colors;
  border-color: var(--c-border);
  color: var(--c-text-dim);
}
.add-step-btn:hover {
  border-color: #fbbf24;
  color: #fbbf24;
}

/* Ações */
.btn-cancel {
  @apply inline-flex items-center rounded-xl border px-4 py-2 text-sm font-medium transition-colors;
  border-color: var(--c-border);
  color: var(--c-text-muted);
}
.btn-cancel:hover { color: var(--c-text); }

.btn-save {
  @apply inline-flex items-center gap-2 rounded-xl px-5 py-2 text-sm font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-60;
  background: #fbbf24;
  color: #1c1917;
}
.btn-save:not(:disabled):hover { background: #f59e0b; }
</style>
