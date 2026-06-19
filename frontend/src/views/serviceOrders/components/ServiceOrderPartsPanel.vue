<script setup lang="ts">
import { ref, watch } from 'vue'
import { listParts, addPartToServiceOrder, removePartFromServiceOrder, type Part } from '../../../api/parts'
import type { ServiceOrderPart } from '../../../api/parts'

const props = defineProps<{
  serviceOrderId: number
  parts: ServiceOrderPart[]
  canManage: boolean
}>()

const emit = defineEmits<{
  (e: 'updated', parts: ServiceOrderPart[]): void
}>()

// Estado local das peças (reativo à prop)
const localParts = ref<ServiceOrderPart[]>([...props.parts])
watch(() => props.parts, (v) => { localParts.value = [...v] })

// Busca de peças do catálogo
const searchQuery = ref('')
const searchResults = ref<Part[]>([])
const searching = ref(false)
const showSearch = ref(false)

// Form de adição
const selectedPart = ref<Part | null>(null)
const addQty = ref(1)
const addNotes = ref('')
const adding = ref(false)

let searchTimer: ReturnType<typeof setTimeout> | null = null

async function doSearch(q: string) {
  if (q.length < 1) { searchResults.value = []; return }
  searching.value = true
  try {
    const res = await listParts({ q, active: true, per_page: 10 })
    searchResults.value = res.data
  } finally {
    searching.value = false
  }
}

function onSearchInput() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => doSearch(searchQuery.value.trim()), 200)
}

function selectPart(part: Part) {
  selectedPart.value = part
  addQty.value = 1
  addNotes.value = ''
  searchQuery.value = part.name
  searchResults.value = []
}

function clearSelection() {
  selectedPart.value = null
  searchQuery.value = ''
  searchResults.value = []
  addQty.value = 1
  addNotes.value = ''
}

async function addPart() {
  if (!selectedPart.value || addQty.value <= 0) return
  adding.value = true
  try {
    const res = await addPartToServiceOrder(props.serviceOrderId, {
      part_id: selectedPart.value.id,
      quantity: addQty.value,
      notes: addNotes.value || undefined,
    })
    localParts.value = [...localParts.value, res.data]
    emit('updated', localParts.value)
    clearSelection()
    showSearch.value = false
  } catch (e: unknown) {
    const err = e as { data?: { message?: string } }
    alert(err.data?.message ?? 'Erro ao adicionar peça.')
  } finally {
    adding.value = false
  }
}

async function removePart(sop: ServiceOrderPart) {
  if (!confirm(`Remover "${sop.part_name}" desta OS? A quantidade voltará ao estoque.`)) return
  try {
    await removePartFromServiceOrder(props.serviceOrderId, sop.id)
    localParts.value = localParts.value.filter((p) => p.id !== sop.id)
    emit('updated', localParts.value)
  } catch (e: unknown) {
    const err = e as { data?: { message?: string } }
    alert(err.data?.message ?? 'Erro ao remover peça.')
  }
}

function fmtMoney(cents: number): string {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

const totalCents = () => localParts.value.reduce((acc, p) => acc + p.unit_sale_price_cents * p.quantity, 0)
</script>

<template>
  <div class="rounded-2xl border" style="border-color: var(--c-border)">
    <!-- Header do painel -->
    <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color: var(--c-border)">
      <div class="flex items-center gap-2">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: var(--c-text-muted)">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
        </svg>
        <h3 class="font-medium text-sm" style="color: var(--c-text)">
          Peças Utilizadas
          <span v-if="localParts.length > 0" class="ml-1 text-xs" style="color: var(--c-text-muted)">({{ localParts.length }})</span>
        </h3>
      </div>
      <button
        v-if="canManage && !showSearch"
        class="btn-ghost text-xs px-3 py-1.5"
        @click="showSearch = true"
      >
        + Adicionar peça
      </button>
    </div>

    <!-- Formulário de busca/adição -->
    <div v-if="showSearch && canManage" class="border-b p-4 space-y-3" style="border-color: var(--c-border); background: var(--c-nav-hover)">
      <div class="relative">
        <input
          v-model="searchQuery"
          type="text"
          class="form-input w-full pr-8"
          placeholder="Buscar peça por nome ou SKU..."
          @input="onSearchInput"
        />
        <button
          v-if="searchQuery || selectedPart"
          class="absolute right-2 top-1/2 -translate-y-1/2 text-xs"
          style="color: var(--c-text-dim)"
          @click="clearSelection"
        >✕</button>

        <!-- Dropdown de resultados -->
        <div
          v-if="searchResults.length > 0"
          class="absolute z-20 left-0 right-0 top-full mt-1 rounded-xl border shadow-lg overflow-hidden"
          style="background: var(--c-sidebar); border-color: var(--c-border)"
        >
          <button
            v-for="part in searchResults"
            :key="part.id"
            class="flex items-center justify-between w-full px-4 py-2.5 text-left text-sm hover:bg-white/5 transition-colors"
            @click="selectPart(part)"
          >
            <div>
              <span style="color: var(--c-text)">{{ part.name }}</span>
              <span v-if="part.sku" class="ml-2 font-mono text-xs" style="color: var(--c-text-dim)">{{ part.sku }}</span>
            </div>
            <span
              class="text-xs tabular-nums"
              :class="part.is_low_stock ? 'text-amber-400' : ''"
              :style="!part.is_low_stock ? { color: 'var(--c-text-muted)' } : {}"
            >
              {{ part.stock_quantity }} {{ part.unit }}
            </span>
          </button>
        </div>
      </div>

      <!-- Quantidade + Observação após seleção -->
      <div v-if="selectedPart" class="space-y-3 rounded-xl border p-3" style="border-color: var(--c-border)">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium" style="color: var(--c-text)">{{ selectedPart.name }}</span>
          <span class="text-xs" style="color: var(--c-text-muted)">
            Estoque: {{ selectedPart.stock_quantity }} {{ selectedPart.unit }}
          </span>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="form-label text-xs">Quantidade</label>
            <input
              v-model.number="addQty"
              type="number"
              :min="0.001"
              :max="selectedPart.stock_quantity"
              step="0.001"
              class="form-input w-full font-mono"
            />
          </div>
          <div>
            <label class="form-label text-xs">Observação (opcional)</label>
            <input v-model="addNotes" type="text" class="form-input w-full" maxlength="500" />
          </div>
        </div>
        <div class="flex gap-2">
          <button class="btn-primary text-sm py-1.5 px-4" :disabled="adding || addQty <= 0" @click="addPart">
            {{ adding ? 'Adicionando...' : 'Confirmar' }}
          </button>
          <button class="btn-ghost text-sm py-1.5 px-3" @click="showSearch = false; clearSelection()">
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <!-- Lista de peças -->
    <div v-if="localParts.length === 0 && !showSearch" class="px-5 py-6 text-center text-sm" style="color: var(--c-text-dim)">
      Nenhuma peça vinculada a esta OS.
    </div>

    <div v-if="localParts.length > 0">
      <div
        v-for="sop in localParts"
        :key="sop.id"
        class="flex items-center justify-between gap-4 px-5 py-3 border-b last:border-0"
        style="border-color: var(--c-border)"
      >
        <div class="min-w-0 flex-1">
          <p class="text-sm font-medium truncate" style="color: var(--c-text)">{{ sop.part_name }}</p>
          <p class="text-xs" style="color: var(--c-text-dim)">
            <span v-if="sop.part_sku" class="font-mono">{{ sop.part_sku }} · </span>
            {{ sop.quantity.toLocaleString('pt-BR') }} {{ sop.part_unit }}
            <span v-if="sop.notes"> · {{ sop.notes }}</span>
          </p>
        </div>
        <div class="text-right shrink-0">
          <p class="text-sm font-semibold tabular-nums" style="color: var(--c-text)">
            {{ fmtMoney(sop.unit_sale_price_cents * sop.quantity) }}
          </p>
          <p class="text-xs" style="color: var(--c-text-dim)">
            {{ fmtMoney(sop.unit_sale_price_cents) }} / {{ sop.part_unit }}
          </p>
        </div>
        <button
          v-if="canManage"
          class="shrink-0 text-xs text-red-400 hover:underline"
          @click="removePart(sop)"
        >
          Remover
        </button>
      </div>

      <!-- Total das peças -->
      <div class="flex items-center justify-between px-5 py-3 border-t" style="border-color: var(--c-border); background: var(--c-nav-hover)">
        <span class="text-xs font-medium" style="color: var(--c-text-muted)">Total em peças</span>
        <span class="text-sm font-bold tabular-nums" style="color: var(--c-text)">{{ fmtMoney(totalCents()) }}</span>
      </div>
    </div>
  </div>
</template>
