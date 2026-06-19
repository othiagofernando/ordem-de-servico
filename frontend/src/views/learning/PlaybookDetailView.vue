<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useTenantRouting } from '../../composables/useTenantRouting'
import { getPlaybook, type Playbook, type PlaybookStep } from '../../api/playbooks'

const route = useRoute()
const { tenantRoute } = useTenantRouting()

const playbook = ref<Playbook | null>(null)
const loading = ref(true)
const currentStepIndex = ref(0)

// Somente leitura no escopo tenant — gestão é feita pelo admin da plataforma

const currentStep = computed<PlaybookStep | null>(
  () => playbook.value?.steps[currentStepIndex.value] ?? null,
)

const progress = computed(() => {
  const total = playbook.value?.steps.length ?? 0
  if (total === 0) return 0
  return Math.round(((currentStepIndex.value + 1) / total) * 100)
})

function goTo(index: number) {
  if (!playbook.value) return
  if (index >= 0 && index < playbook.value.steps.length) {
    currentStepIndex.value = index
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

function prev() { goTo(currentStepIndex.value - 1) }
function next() { goTo(currentStepIndex.value + 1) }

onMounted(async () => {
  try {
    const res = await getPlaybook(Number(route.params.id))
    playbook.value = res.data
  } finally {
    loading.value = false
  }
})

const tipConfig = {
  info: {
    bg: 'rgba(96,165,250,0.1)',
    border: 'rgba(96,165,250,0.3)',
    color: '#93c5fd',
    icon: 'M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
    label: 'Informação',
  },
  warning: {
    bg: 'rgba(245,158,11,0.1)',
    border: 'rgba(245,158,11,0.3)',
    color: '#fcd34d',
    icon: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
    label: 'Atenção',
  },
  success: {
    bg: 'rgba(52,211,153,0.1)',
    border: 'rgba(52,211,153,0.3)',
    color: '#6ee7b7',
    icon: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    label: 'Dica',
  },
}

function tipCfg(type: string) {
  return tipConfig[type as keyof typeof tipConfig] ?? tipConfig.info
}
</script>

<template>
  <div>
    <!-- Voltar -->
    <RouterLink
      :to="tenantRoute('learning')"
      class="mb-6 inline-flex items-center gap-1.5 text-sm transition-colors hover:underline"
      style="color: var(--c-text-muted)"
    >
      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
      </svg>
      Aprendizado
    </RouterLink>

    <!-- Loading -->
    <div v-if="loading" class="space-y-4">
      <div class="h-10 w-3/4 rounded-xl card-skeleton" />
      <div class="h-4 w-1/2 rounded card-skeleton" />
      <div class="mt-8 h-96 rounded-2xl card-skeleton" />
    </div>

    <template v-else-if="playbook">
      <!-- Header do playbook -->
      <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-4">
          <span class="mt-1 text-4xl leading-none">{{ playbook.emoji || '📖' }}</span>
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h1 class="text-2xl font-bold" style="color: var(--c-text)">{{ playbook.title }}</h1>
              <span
                v-if="playbook.status === 'draft'"
                class="rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide"
                style="background: var(--c-surface-hover); color: var(--c-text-dim)"
              >
                Rascunho
              </span>
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-2">
              <span
                v-if="playbook.category"
                class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                style="background: rgba(251,191,36,0.12); color: #fbbf24"
              >
                {{ playbook.category }}
              </span>
              <span class="text-sm" style="color: var(--c-text-muted)">
                {{ playbook.steps.length }} {{ playbook.steps.length === 1 ? 'passo' : 'passos' }}
              </span>
            </div>
            <p v-if="playbook.description" class="mt-2 text-sm" style="color: var(--c-text-muted)">
              {{ playbook.description }}
            </p>
          </div>
        </div>
      </div>

      <!-- Barra de progresso geral -->
      <div class="mb-6 rounded-2xl border p-4" style="background: var(--c-surface); border-color: var(--c-border)">
        <div class="mb-2 flex items-center justify-between text-sm">
          <span style="color: var(--c-text-muted)">Progresso</span>
          <span class="font-semibold" style="color: var(--c-text)">
            Passo {{ currentStepIndex + 1 }} de {{ playbook.steps.length }}
          </span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full" style="background: var(--c-surface-hover)">
          <div
            class="h-2 rounded-full transition-all duration-500"
            style="background: #fbbf24"
            :style="{ width: `${progress}%` }"
          />
        </div>
        <!-- Steps como dots clicáveis -->
        <div class="mt-3 flex flex-wrap gap-1.5">
          <button
            v-for="(step, i) in playbook.steps"
            :key="step.id"
            class="step-dot"
            :class="{ 'step-dot-active': i === currentStepIndex, 'step-dot-done': i < currentStepIndex }"
            :title="step.title"
            @click="goTo(i)"
          >
            {{ i + 1 }}
          </button>
        </div>
      </div>

      <!-- Layout principal: TOC + Conteúdo -->
      <div class="flex gap-6 lg:items-start">
        <!-- Sidebar TOC (desktop) -->
        <aside class="hidden w-64 shrink-0 lg:block">
          <div class="sticky top-20 rounded-2xl border p-3" style="background: var(--c-surface); border-color: var(--c-border)">
            <p class="mb-2 px-2 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--c-text-dim)">
              Conteúdo
            </p>
            <nav class="space-y-0.5">
              <button
                v-for="(step, i) in playbook.steps"
                :key="step.id"
                class="toc-item w-full text-left"
                :class="{ 'toc-item-active': i === currentStepIndex }"
                @click="goTo(i)"
              >
                <span
                  class="toc-num"
                  :class="{
                    'toc-num-active': i === currentStepIndex,
                    'toc-num-done': i < currentStepIndex,
                  }"
                >
                  <template v-if="i < currentStepIndex">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                  </template>
                  <template v-else>{{ i + 1 }}</template>
                </span>
                <span class="min-w-0 flex-1 truncate">{{ step.title }}</span>
              </button>
            </nav>
          </div>
        </aside>

        <!-- Conteúdo do Step -->
        <div class="min-w-0 flex-1">
          <div
            v-if="currentStep"
            class="rounded-2xl border p-6 sm:p-8"
            style="background: var(--c-surface); border-color: var(--c-border)"
          >
            <!-- Número + Título do step -->
            <div class="mb-6 flex items-center gap-4">
              <div class="step-number-badge">{{ currentStepIndex + 1 }}</div>
              <h2 class="text-xl font-bold" style="color: var(--c-text)">{{ currentStep.title }}</h2>
            </div>

            <!-- Conteúdo principal -->
            <div
              class="step-content prose-custom mb-6 text-base leading-relaxed"
              style="color: var(--c-text-muted)"
            >
              <p
                v-for="(paragraph, pi) in currentStep.content.split('\n').filter(Boolean)"
                :key="pi"
                class="mb-3 last:mb-0"
              >
                {{ paragraph }}
              </p>
            </div>

            <!-- Tip / Dica -->
            <div
              v-if="currentStep.tip"
              class="mt-6 flex gap-3 rounded-xl border p-4"
              :style="{
                background: tipCfg(currentStep.tip_type).bg,
                borderColor: tipCfg(currentStep.tip_type).border,
              }"
            >
              <div class="mt-0.5 shrink-0">
                <svg
                  class="h-5 w-5"
                  :style="{ color: tipCfg(currentStep.tip_type).color }"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" :d="tipCfg(currentStep.tip_type).icon" />
                </svg>
              </div>
              <div>
                <p
                  class="mb-0.5 text-xs font-semibold uppercase tracking-wide"
                  :style="{ color: tipCfg(currentStep.tip_type).color }"
                >
                  {{ tipCfg(currentStep.tip_type).label }}
                </p>
                <p class="text-sm leading-relaxed" style="color: var(--c-text)">
                  {{ currentStep.tip }}
                </p>
              </div>
            </div>

            <!-- Navegação -->
            <div class="mt-8 flex items-center justify-between border-t pt-6" style="border-color: var(--c-border)">
              <button
                class="nav-btn"
                :disabled="currentStepIndex === 0"
                @click="prev"
              >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Anterior
              </button>

              <span class="text-sm" style="color: var(--c-text-dim)">
                {{ currentStepIndex + 1 }} / {{ playbook.steps.length }}
              </span>

              <button
                v-if="currentStepIndex < playbook.steps.length - 1"
                class="nav-btn-primary"
                @click="next"
              >
                Próximo
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
              </button>

              <div
                v-else
                class="flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: rgba(52,211,153,0.15); color: #6ee7b7"
              >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Playbook concluído!
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Playbook não encontrado -->
    <div v-else class="py-16 text-center">
      <p style="color: var(--c-text-muted)">Playbook não encontrado.</p>
      <RouterLink :to="tenantRoute('learning')" class="mt-4 inline-block text-sm hover:underline" style="color: #fbbf24">
        Voltar para Aprendizado
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.card-skeleton {
  background: var(--c-surface);
  animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

.btn-outline {
  @apply inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold transition-colors;
  border-color: var(--c-border);
  color: var(--c-text-muted);
}
.btn-outline:hover {
  color: var(--c-text);
  border-color: var(--c-text-muted);
}

/* Dots de progresso */
.step-dot {
  @apply flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold transition-all;
  background: var(--c-surface-hover);
  color: var(--c-text-dim);
}
.step-dot:hover {
  color: var(--c-text);
}
.step-dot-active {
  background: #fbbf24 !important;
  color: #1c1917 !important;
  transform: scale(1.1);
}
.step-dot-done {
  background: rgba(52, 211, 153, 0.2) !important;
  color: #6ee7b7 !important;
}

/* TOC */
.toc-item {
  @apply flex items-center gap-2.5 rounded-lg px-2 py-2 text-sm transition-colors;
  color: var(--c-text-muted);
}
.toc-item:hover {
  background: var(--c-nav-hover);
  color: var(--c-text);
}
.toc-item-active {
  background: var(--c-nav-active) !important;
  color: #fbbf24 !important;
  font-weight: 500;
}

.toc-num {
  @apply flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold transition-all;
  background: var(--c-surface-hover);
  color: var(--c-text-dim);
}
.toc-num-active {
  background: #fbbf24 !important;
  color: #1c1917 !important;
}
.toc-num-done {
  background: rgba(52, 211, 153, 0.2) !important;
  color: #6ee7b7 !important;
}

/* Número do step */
.step-number-badge {
  @apply flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg font-bold;
  background: rgba(251, 191, 36, 0.15);
  color: #fbbf24;
}

/* Botões de navegação */
.nav-btn {
  @apply inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40;
  border-color: var(--c-border);
  color: var(--c-text-muted);
}
.nav-btn:not(:disabled):hover {
  color: var(--c-text);
  border-color: var(--c-text-muted);
}

.nav-btn-primary {
  @apply inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors;
  background: #fbbf24;
  color: #1c1917;
}
.nav-btn-primary:hover {
  background: #f59e0b;
}
</style>
