<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import {
  listChecklistTemplates,
  createChecklistTemplate,
  type ChecklistTemplate,
  type ChecklistTemplateItem,
} from '../../api/checklistTemplates'
import { ApiError } from '../../api/client'

const loading = ref(true)
const templates = ref<ChecklistTemplate[]>([])
const error = ref('')
const showForm = ref(false)
const saving = ref(false)
const expandedId = ref<number | null>(null)

const form = ref({
  category: '',
  title: '',
  items: [{ key: '', label: '', type: 'boolean', required: false }] as ChecklistTemplateItem[],
})

const itemTypes = [
  { value: 'boolean', label: 'Sim/Não' },
  { value: 'text', label: 'Texto livre' },
  { value: 'rating', label: 'Avaliação' },
]

const groupedTemplates = computed(() => {
  const groups: Record<string, ChecklistTemplate[]> = {}
  for (const t of templates.value) {
    if (!groups[t.category]) groups[t.category] = []
    groups[t.category].push(t)
  }
  return groups
})

onMounted(async () => {
  await load()
})

async function load() {
  try {
    const res = await listChecklistTemplates()
    templates.value = res.data
  } catch {
    error.value = 'Não foi possível carregar os templates.'
  } finally {
    loading.value = false
  }
}

function addItem() {
  form.value.items.push({ key: '', label: '', type: 'boolean', required: false })
}

function removeItem(i: number) {
  form.value.items.splice(i, 1)
}

function generateKey(label: string) {
  return label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
}

function autoKey(item: ChecklistTemplateItem) {
  if (!item.key && item.label) {
    item.key = generateKey(item.label)
  }
}

async function save() {
  error.value = ''
  saving.value = true
  try {
    const payload = {
      ...form.value,
      items: form.value.items.map(it => ({
        ...it,
        key: it.key || generateKey(it.label),
      })),
    }
    await createChecklistTemplate(payload)
    await load()
    showForm.value = false
    form.value = {
      category: '',
      title: '',
      items: [{ key: '', label: '', type: 'boolean', required: false }],
    }
  } catch (e) {
    if (e instanceof ApiError) {
      const errData = e.data as { message?: string; errors?: Record<string, string[]> }
      const first = errData.errors ? Object.values(errData.errors)[0]?.[0] : null
      error.value = first ?? errData.message ?? 'Erro ao salvar.'
    } else {
      error.value = 'Erro inesperado.'
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-white">Templates de Checklist</h1>
      <button
        class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
        @click="showForm = !showForm"
      >
        {{ showForm ? 'Cancelar' : '+ Novo Template' }}
      </button>
    </div>

    <p class="text-sm text-stone-400">
      Templates definem o checklist de recebimento para cada categoria de equipamento. Cada versão é imutável — para alterar, crie uma nova versão.
    </p>

    <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ error }}</div>

    <!-- Formulário novo template -->
    <div v-if="showForm" class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-6 space-y-4">
      <h2 class="text-sm font-semibold text-amber-300">Novo Template</h2>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1.5 block text-sm text-stone-300">Categoria do Equipamento *</label>
          <input v-model="form.category" class="input" placeholder="ex: notebook, smartphone" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm text-stone-300">Título do Template *</label>
          <input v-model="form.title" class="input" placeholder="ex: Checklist Padrão Notebook" />
        </div>
      </div>

      <div>
        <div class="mb-3 flex items-center justify-between">
          <p class="text-sm font-semibold text-stone-300">Itens do Checklist</p>
          <button type="button" class="text-sm text-amber-400 hover:text-amber-300" @click="addItem">+ Adicionar item</button>
        </div>
        <div class="space-y-2">
          <div v-for="(item, i) in form.items" :key="i" class="grid items-center gap-2 sm:grid-cols-[1fr_1fr_100px_80px_auto]">
            <input
              v-model="item.label"
              placeholder="Rótulo do item"
              class="input"
              @blur="autoKey(item)"
            />
            <input v-model="item.key" placeholder="Chave (gerada auto)" class="input font-mono text-xs" />
            <select v-model="item.type" class="input">
              <option v-for="t in itemTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
            <label class="flex items-center gap-1.5 text-xs text-stone-400 cursor-pointer">
              <input v-model="item.required" type="checkbox" class="accent-amber-500" />
              Obrig.
            </label>
            <button
              v-if="form.items.length > 1"
              type="button"
              class="text-red-400 hover:text-red-300"
              @click="removeItem(i)"
            >✕</button>
          </div>
        </div>
      </div>

      <div class="flex gap-2">
        <button
          :disabled="saving || !form.category.trim() || !form.title.trim() || form.items.some(i => !i.label.trim())"
          class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
          @click="save"
        >
          {{ saving ? 'Salvando…' : 'Criar Template' }}
        </button>
        <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="showForm = false">Cancelar</button>
      </div>
    </div>

    <!-- Lista de templates -->
    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

    <div v-else-if="Object.keys(groupedTemplates).length === 0" class="rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-stone-500">
      Nenhum template cadastrado. Crie o primeiro template acima.
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="(group, category) in groupedTemplates"
        :key="category"
        class="rounded-2xl border border-white/10 bg-white/5 overflow-hidden"
      >
        <div class="border-b border-white/10 bg-white/5 px-6 py-3">
          <span class="text-xs font-semibold uppercase tracking-widest text-stone-400">{{ category }}</span>
          <span class="ml-2 text-xs text-stone-600">{{ group.length }} versão(ões)</span>
        </div>
        <div class="divide-y divide-white/5">
          <div
            v-for="t in group"
            :key="t.id"
            class="cursor-pointer transition hover:bg-white/3"
            @click="expandedId = expandedId === t.id ? null : t.id"
          >
            <div class="flex items-center justify-between px-6 py-4">
              <div>
                <p class="text-sm font-medium text-stone-200">{{ t.title }}</p>
                <p class="text-xs text-stone-500">Versão {{ t.version }} · {{ t.items.length }} itens</p>
              </div>
              <span class="text-stone-500">{{ expandedId === t.id ? '▲' : '▼' }}</span>
            </div>
            <div v-if="expandedId === t.id" class="px-6 pb-4">
              <div class="space-y-1.5">
                <div v-for="item in t.items" :key="item.key" class="flex items-center gap-3 text-sm">
                  <span class="w-16 shrink-0 rounded-full bg-white/5 px-2 py-0.5 text-center text-xs text-stone-400">
                    {{ item.type === 'boolean' ? 'S/N' : item.type === 'text' ? 'Texto' : 'Avaliação' }}
                  </span>
                  <span class="text-stone-300">{{ item.label }}</span>
                  <span v-if="item.required" class="text-xs text-amber-500">*</span>
                </div>
              </div>
            </div>
          </div>
        </div>
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
