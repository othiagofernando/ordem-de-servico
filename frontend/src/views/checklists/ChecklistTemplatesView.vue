<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
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
const categoryFilter = ref('')

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

const filteredTemplates = computed(() => {
  const category = categoryFilter.value.trim().toLowerCase()

  if (!category) {
    return templates.value
  }

  return templates.value.filter((template) => template.category.toLowerCase().includes(category))
})

const groupedTemplates = computed(() => {
  const groups: Record<string, ChecklistTemplate[]> = {}
  for (const template of filteredTemplates.value) {
    if (!groups[template.category]) groups[template.category] = []
    groups[template.category].push(template)
  }
  return groups
})

const categories = computed(() => Array.from(new Set(templates.value.map((template) => template.category))))
const latestVersions = computed(() => {
  const latest = new Map<string, number>()

  for (const template of templates.value) {
    latest.set(template.category, Math.max(latest.get(template.category) ?? 0, template.version))
  }

  return latest
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

function removeItem(index: number) {
  form.value.items.splice(index, 1)
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
      items: form.value.items.map((item) => ({
        ...item,
        key: item.key || generateKey(item.label),
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
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Templates de checklist</h1>
        <p class="mt-1 text-sm text-stone-400">
          Definem o checklist de recebimento por categoria de equipamento e alimentam a abertura da O.S. com snapshot versionado.
        </p>
      </div>
      <button
        class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
        @click="showForm = !showForm"
      >
        {{ showForm ? 'Cancelar' : '+ Novo template' }}
      </button>
    </div>

    <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ error }}</div>

    <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
      <div class="space-y-6">
        <div v-if="templates.length === 0" class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-400">Como isso entra no fluxo</h2>
          <div class="grid gap-3 md:grid-cols-3">
            <div class="rounded-xl border border-white/10 bg-black/20 p-4">
              <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">1. Categoria</p>
              <p class="mt-2 text-sm text-stone-300">O template é criado por categoria de equipamento, como `notebook` ou `smartphone`.</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-black/20 p-4">
              <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">2. Intake</p>
              <p class="mt-2 text-sm text-stone-300">Na abertura da O.S., a equipe carrega o template compatível e marca o estado de recebimento.</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-black/20 p-4">
              <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">3. Snapshot</p>
              <p class="mt-2 text-sm text-stone-300">Ao abrir a ordem, a versão usada fica registrada na O.S., mesmo que novos templates sejam criados depois.</p>
            </div>
          </div>
          <div class="mt-4 flex flex-wrap gap-2">
            <RouterLink :to="{ name: 'service-orders.create' }" class="rounded-lg border border-amber-500/30 px-3 py-1.5 text-xs text-amber-300 hover:bg-amber-500/10">
              Abrir nova O.S.
            </RouterLink>
          </div>
        </div>

        <div v-if="showForm" class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-6 space-y-4">
          <h2 class="text-sm font-semibold text-amber-300">Novo template</h2>

          <div class="grid gap-4 sm:grid-cols-2">
            <label class="space-y-1">
              <span class="text-sm text-stone-300">Categoria do equipamento *</span>
              <input v-model="form.category" class="input" placeholder="ex: notebook, smartphone" />
            </label>
            <label class="space-y-1">
              <span class="text-sm text-stone-300">Título do template *</span>
              <input v-model="form.title" class="input" placeholder="ex: Checklist padrão notebook" />
            </label>
          </div>

          <div>
            <div class="mb-3 flex items-center justify-between">
              <p class="text-sm font-semibold text-stone-300">Itens do checklist</p>
              <button type="button" class="text-sm text-amber-400 hover:text-amber-300" @click="addItem">+ Adicionar item</button>
            </div>
            <div class="space-y-2">
              <div v-for="(item, index) in form.items" :key="index" class="grid items-center gap-2 sm:grid-cols-[1fr_1fr_110px_90px_auto]">
                <input v-model="item.label" placeholder="Rótulo do item" class="input" @blur="autoKey(item)" />
                <input v-model="item.key" placeholder="Chave (auto)" class="input font-mono text-xs" />
                <select v-model="item.type" class="input">
                  <option v-for="type in itemTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>
                <label class="flex items-center gap-1.5 text-xs text-stone-400 cursor-pointer">
                  <input v-model="item.required" type="checkbox" class="accent-amber-500" />
                  Obrig.
                </label>
                <button v-if="form.items.length > 1" type="button" class="text-red-400 hover:text-red-300" @click="removeItem(index)">✕</button>
              </div>
            </div>
          </div>

          <div class="flex gap-2">
            <button
              :disabled="saving || !form.category.trim() || !form.title.trim() || form.items.some((item) => !item.label.trim())"
              class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
              @click="save"
            >
              {{ saving ? 'Salvando…' : 'Criar template' }}
            </button>
            <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="showForm = false">Cancelar</button>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-400">Catálogo</h2>
          <label class="space-y-1">
            <span class="text-xs font-semibold uppercase tracking-widest text-stone-500">Filtrar categoria</span>
            <input v-model="categoryFilter" class="input" placeholder="Ex.: notebook" />
          </label>
          <div class="mt-4 flex flex-wrap gap-2">
            <button
              v-for="category in categories"
              :key="category"
              type="button"
              class="rounded-full border border-white/10 px-3 py-1 text-xs text-stone-300 hover:border-amber-500/30 hover:text-amber-300"
              @click="categoryFilter = category"
            >
              {{ category }} · v{{ latestVersions.get(category) ?? 1 }}
            </button>
          </div>
        </div>

        <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

        <div v-else-if="Object.keys(groupedTemplates).length === 0" class="rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-stone-500">
          Nenhum template encontrado para o filtro atual.
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
                v-for="template in group"
                :key="template.id"
                class="cursor-pointer transition hover:bg-white/3"
                @click="expandedId = expandedId === template.id ? null : template.id"
              >
                <div class="flex items-center justify-between px-6 py-4">
                  <div>
                    <p class="text-sm font-medium text-stone-200">{{ template.title }}</p>
                    <p class="text-xs text-stone-500">Versão {{ template.version }} · {{ template.items.length }} itens</p>
                  </div>
                  <span class="text-stone-500">{{ expandedId === template.id ? '▲' : '▼' }}</span>
                </div>
                <div v-if="expandedId === template.id" class="px-6 pb-4">
                  <div class="space-y-1.5">
                    <div v-for="item in template.items" :key="item.key" class="flex items-center gap-3 text-sm">
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
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

</style>
