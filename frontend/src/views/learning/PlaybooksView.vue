<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useTenantRouting } from '../../composables/useTenantRouting'
import { listPlaybooks, type Playbook } from '../../api/playbooks'

const { tenantRoute } = useTenantRouting()

const playbooks = ref<Playbook[]>([])
const loading = ref(true)
const q = ref('')
const selectedCategory = ref('')

const categories = computed(() => {
  const all = playbooks.value.map((p) => p.category).filter(Boolean) as string[]
  return [...new Set(all)].sort()
})

const filtered = computed(() => {
  let list = playbooks.value
  if (selectedCategory.value) list = list.filter((p) => p.category === selectedCategory.value)
  if (q.value.trim()) {
    const term = q.value.toLowerCase()
    list = list.filter(
      (p) =>
        p.title.toLowerCase().includes(term) ||
        (p.description ?? '').toLowerCase().includes(term) ||
        (p.category ?? '').toLowerCase().includes(term),
    )
  }
  return list
})

onMounted(async () => {
  try {
    const res = await listPlaybooks()
    playbooks.value = res.data
  } finally {
    loading.value = false
  }
})

function stepLabel(n: number): string {
  return n === 1 ? '1 passo' : `${n} passos`
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-xl font-bold" style="color: var(--c-text)">Aprendizado</h1>
      <p class="mt-0.5 text-sm" style="color: var(--c-text-muted)">
        Guias e procedimentos passo a passo para a equipe
      </p>
    </div>

    <!-- Busca + filtro por categoria -->
    <div class="flex flex-col gap-3 sm:flex-row">
      <div class="relative flex-1">
        <svg
          class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2"
          style="color: var(--c-text-dim)"
          viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
        <input v-model="q" type="text" placeholder="Buscar playbooks..." class="search-input w-full pl-9" />
      </div>
      <select v-if="categories.length > 0" v-model="selectedCategory" class="filter-select">
        <option value="">Todas as categorias</option>
        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="i in 6" :key="i" class="h-44 rounded-2xl card-skeleton" />
    </div>

    <!-- Vazio -->
    <div
      v-else-if="filtered.length === 0"
      class="flex flex-col items-center gap-3 rounded-2xl border border-dashed py-16 text-center"
      style="border-color: var(--c-border)"
    >
      <span class="text-5xl">📖</span>
      <div>
        <p class="font-medium" style="color: var(--c-text)">
          {{ q || selectedCategory ? 'Nenhum resultado encontrado' : 'Nenhum playbook disponível ainda' }}
        </p>
        <p class="mt-1 text-sm" style="color: var(--c-text-muted)">
          {{ q || selectedCategory ? 'Tente outros termos ou remova os filtros.' : 'Os guias de procedimento aparecerão aqui quando forem publicados.' }}
        </p>
      </div>
    </div>

    <!-- Grid de cards -->
    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <RouterLink
        v-for="pb in filtered"
        :key="pb.id"
        :to="tenantRoute('learning.detail', { params: { id: pb.id } })"
        class="playbook-card flex flex-col rounded-2xl border p-5 transition-all hover:shadow-lg"
        style="background: var(--c-surface); border-color: var(--c-border)"
      >
        <!-- Emoji + Título -->
        <div class="mb-3 flex items-start gap-3">
          <span class="text-3xl leading-none">{{ pb.emoji || '📖' }}</span>
          <div class="min-w-0 flex-1">
            <h2 class="font-semibold leading-snug" style="color: var(--c-text)">{{ pb.title }}</h2>
            <span
              v-if="pb.category"
              class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-medium"
              style="background: rgba(251,191,36,0.12); color: #fbbf24"
            >
              {{ pb.category }}
            </span>
          </div>
        </div>

        <!-- Descrição -->
        <p
          v-if="pb.description"
          class="mb-4 flex-1 text-sm leading-relaxed"
          style="color: var(--c-text-muted)"
        >
          {{ pb.description }}
        </p>
        <div v-else class="flex-1" />

        <!-- Footer: passos + seta -->
        <div class="mt-auto flex items-center justify-between border-t pt-3" style="border-color: var(--c-border)">
          <span class="flex items-center gap-1.5 text-xs" style="color: var(--c-text-dim)">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
            </svg>
            {{ stepLabel(pb.steps_count) }}
          </span>
          <span class="flex items-center gap-1 text-xs font-medium" style="color: #fbbf24">
            Abrir
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
            </svg>
          </span>
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.search-input {
  @apply rounded-xl border px-3 py-2 text-sm outline-none transition-colors;
  background: var(--c-surface);
  border-color: var(--c-border);
  color: var(--c-text);
}
.search-input:focus { border-color: #fbbf24; }

.filter-select {
  @apply rounded-xl border px-3 py-2 text-sm outline-none;
  background: var(--c-surface);
  border-color: var(--c-border);
  color: var(--c-text);
}

.card-skeleton {
  background: var(--c-surface);
  animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

.playbook-card:hover {
  border-color: rgba(251, 191, 36, 0.3);
  transform: translateY(-1px);
}
</style>
