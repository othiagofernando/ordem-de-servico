<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { adminListPlaybooks, adminDeletePlaybook, type Playbook } from '../../api/adminPlaybooks'

const playbooks = ref<Playbook[]>([])
const loading = ref(true)
const q = ref('')
const selectedCategory = ref('')
const selectedStatus = ref('all')

const categories = computed(() => {
  const all = playbooks.value.map((p) => p.category).filter(Boolean) as string[]
  return [...new Set(all)].sort()
})

const filtered = computed(() => {
  let list = playbooks.value
  if (selectedStatus.value !== 'all') list = list.filter((p) => p.status === selectedStatus.value)
  if (selectedCategory.value) list = list.filter((p) => p.category === selectedCategory.value)
  if (q.value.trim()) {
    const term = q.value.toLowerCase()
    list = list.filter(
      (p) =>
        p.title.toLowerCase().includes(term) ||
        (p.description ?? '').toLowerCase().includes(term),
    )
  }
  return list
})

const published = computed(() => playbooks.value.filter((p) => p.status === 'published').length)
const drafts = computed(() => playbooks.value.filter((p) => p.status === 'draft').length)

async function load() {
  loading.value = true
  try {
    const res = await adminListPlaybooks({ status: 'all' })
    playbooks.value = res.data
  } finally {
    loading.value = false
  }
}

async function remove(pb: Playbook) {
  if (!confirm(`Excluir "${pb.title}"? Esta ação não pode ser desfeita.`)) return
  await adminDeletePlaybook(pb.id)
  playbooks.value = playbooks.value.filter((p) => p.id !== pb.id)
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Playbooks</h1>
        <p class="mt-1 text-sm text-stone-400">
          Guias de procedimento disponibilizados para todas as assistências da plataforma.
        </p>
      </div>
      <RouterLink
        :to="{ name: 'admin.playbooks.create' }"
        class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
      >
        + Novo Playbook
      </RouterLink>
    </div>

    <!-- Stats -->
    <div class="grid gap-4 sm:grid-cols-3">
      <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-300">Publicados</p>
        <p class="mt-1.5 text-3xl font-bold text-white">{{ published }}</p>
      </div>
      <div class="rounded-2xl border border-stone-700 bg-stone-800/50 p-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-stone-400">Rascunhos</p>
        <p class="mt-1.5 text-3xl font-bold text-white">{{ drafts }}</p>
      </div>
      <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">Total</p>
        <p class="mt-1.5 text-3xl font-bold text-white">{{ playbooks.length }}</p>
      </div>
    </div>

    <!-- Filtros -->
    <div class="flex flex-col gap-3 sm:flex-row">
      <input
        v-model="q"
        type="text"
        placeholder="Buscar por título ou descrição..."
        class="flex-1 rounded-xl border border-stone-700 bg-stone-800 px-3 py-2 text-sm text-white outline-none placeholder:text-stone-500 focus:border-amber-500"
      />
      <select
        v-model="selectedCategory"
        class="rounded-xl border border-stone-700 bg-stone-800 px-3 py-2 text-sm text-white outline-none"
      >
        <option value="">Todas as categorias</option>
        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
      </select>
      <select
        v-model="selectedStatus"
        class="rounded-xl border border-stone-700 bg-stone-800 px-3 py-2 text-sm text-white outline-none"
      >
        <option value="all">Todos</option>
        <option value="published">Publicados</option>
        <option value="draft">Rascunhos</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="h-20 animate-pulse rounded-2xl bg-stone-800" />
    </div>

    <!-- Vazio -->
    <div
      v-else-if="filtered.length === 0"
      class="rounded-2xl border border-dashed border-stone-700 py-16 text-center"
    >
      <p class="text-stone-400">
        {{ q || selectedCategory || selectedStatus !== 'all' ? 'Nenhum resultado.' : 'Nenhum playbook cadastrado.' }}
      </p>
      <RouterLink
        v-if="!q && !selectedCategory && selectedStatus === 'all'"
        :to="{ name: 'admin.playbooks.create' }"
        class="mt-4 inline-block text-sm text-amber-400 hover:underline"
      >
        Criar o primeiro playbook
      </RouterLink>
    </div>

    <!-- Tabela -->
    <div v-else class="overflow-hidden rounded-2xl border border-stone-700">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-stone-700 bg-stone-800/60">
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-stone-400">Playbook</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-stone-400">Categoria</th>
            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-stone-400">Passos</th>
            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-stone-400">Status</th>
            <th class="px-4 py-3" />
          </tr>
        </thead>
        <tbody class="divide-y divide-stone-800">
          <tr v-for="pb in filtered" :key="pb.id" class="bg-stone-900 transition hover:bg-stone-800/40">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2.5">
                <span class="text-xl">{{ pb.emoji || '📖' }}</span>
                <div>
                  <p class="font-medium text-white">{{ pb.title }}</p>
                  <p v-if="pb.description" class="mt-0.5 max-w-xs truncate text-xs text-stone-500">{{ pb.description }}</p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3">
              <span v-if="pb.category" class="rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-400">
                {{ pb.category }}
              </span>
              <span v-else class="text-xs text-stone-600">—</span>
            </td>
            <td class="px-4 py-3 text-center text-stone-300">{{ pb.steps_count }}</td>
            <td class="px-4 py-3 text-center">
              <span
                class="rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide"
                :class="pb.status === 'published' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-stone-700 text-stone-400'"
              >
                {{ pb.status === 'published' ? 'Publicado' : 'Rascunho' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-3">
                <RouterLink
                  :to="{ name: 'admin.playbooks.edit', params: { id: pb.id } }"
                  class="text-xs text-stone-400 transition hover:text-white"
                >
                  Editar
                </RouterLink>
                <button
                  class="text-xs text-stone-500 transition hover:text-red-400"
                  @click="remove(pb)"
                >
                  Excluir
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
