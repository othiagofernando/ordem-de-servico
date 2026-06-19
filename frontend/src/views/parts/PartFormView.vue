<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTenantRouting } from '../../composables/useTenantRouting'
import { getPart, createPart, updatePart, adjustStock, type PartPayload } from '../../api/parts'

const route = useRoute()
const router = useRouter()
const { tenantRoute } = useTenantRouting()

const isEdit = computed(() => !!route.params.id)
const partId = computed(() => Number(route.params.id))

const saving = ref(false)
const loading = ref(false)
const errors = ref<Record<string, string>>({})

// Ajuste de estoque
const showStockModal = ref(false)
const stockAdjustQty = ref(0)
const stockAdjustNotes = ref('')
const stockAdjusting = ref(false)
const currentStock = ref(0)

const form = ref<PartPayload & { active: boolean }>({
  name: '',
  description: null,
  category: null,
  sku: null,
  unit: 'un',
  stock_quantity: 0,
  min_stock_quantity: 0,
  cost_price_cents: 0,
  sale_price_cents: 0,
  active: true,
})

const UNITS = ['un', 'par', 'm', 'cm', 'g', 'kg', 'ml', 'l']

const costFormatted = ref('')
const saleFormatted = ref('')

function centsToStr(cents: number): string {
  return (cents / 100).toFixed(2).replace('.', ',')
}

function strToCents(str: string): number {
  const n = parseFloat(str.replace(/\./g, '').replace(',', '.'))
  return isNaN(n) ? 0 : Math.round(n * 100)
}

function onCostInput(e: Event) {
  costFormatted.value = (e.target as HTMLInputElement).value
  form.value.cost_price_cents = strToCents(costFormatted.value)
}

function onSaleInput(e: Event) {
  saleFormatted.value = (e.target as HTMLInputElement).value
  form.value.sale_price_cents = strToCents(saleFormatted.value)
}

async function load() {
  if (!isEdit.value) return
  loading.value = true
  try {
    const res = await getPart(partId.value)
    const p = res.data
    form.value = {
      name: p.name,
      description: p.description,
      category: p.category,
      sku: p.sku,
      unit: p.unit,
      stock_quantity: p.stock_quantity,
      min_stock_quantity: p.min_stock_quantity,
      cost_price_cents: p.cost_price_cents,
      sale_price_cents: p.sale_price_cents,
      active: p.active,
    }
    currentStock.value = p.stock_quantity
    costFormatted.value = centsToStr(p.cost_price_cents)
    saleFormatted.value = centsToStr(p.sale_price_cents)
  } finally {
    loading.value = false
  }
}

async function save() {
  errors.value = {}
  saving.value = true
  try {
    if (isEdit.value) {
      await updatePart(partId.value, form.value)
    } else {
      await createPart(form.value)
    }
    router.push(tenantRoute('parts'))
  } catch (e: unknown) {
    const err = e as { status?: number; data?: { errors?: Record<string, string[]>; message?: string } }
    if (err.status === 422 && err.data?.errors) {
      for (const [field, msgs] of Object.entries(err.data.errors)) {
        errors.value[field] = msgs[0]
      }
    } else {
      alert(err.data?.message ?? 'Erro ao salvar.')
    }
  } finally {
    saving.value = false
  }
}

async function doAdjustStock() {
  if (stockAdjustQty.value === 0) return
  stockAdjusting.value = true
  try {
    const res = await adjustStock(partId.value, {
      quantity: stockAdjustQty.value,
      notes: stockAdjustNotes.value || undefined,
    })
    currentStock.value = res.data.stock_quantity
    form.value.stock_quantity = res.data.stock_quantity
    showStockModal.value = false
    stockAdjustQty.value = 0
    stockAdjustNotes.value = ''
  } catch (e: unknown) {
    const err = e as { data?: { message?: string } }
    alert(err.data?.message ?? 'Erro ao ajustar estoque.')
  } finally {
    stockAdjusting.value = false
  }
}

onMounted(() => {
  costFormatted.value = centsToStr(0)
  saleFormatted.value = centsToStr(0)
  load()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <RouterLink
      :to="tenantRoute('parts')"
      class="text-stone-500 hover:text-stone-200"
    >
      ← Peças e Estoque
    </RouterLink>
    <h1 class="text-2xl font-semibold text-white">
      {{ isEdit ? 'Editar Peça' : 'Nova Peça' }}
    </h1>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

    <form v-else class="space-y-6" @submit.prevent="save">
      <!-- Informações básicas -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <div class="grid gap-4 sm:grid-cols-2">
          <!-- Nome -->
          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-sm text-stone-300">Nome da peça *</label>
            <input
              v-model="form.name"
              type="text"
              class="input"
              :class="{ 'border-red-500': errors.name }"
              maxlength="200"
              required
              placeholder="Ex: Tela LCD, Bateria, Conector USB"
            />
            <p v-if="errors.name" class="mt-1 text-xs text-red-400">{{ errors.name }}</p>
          </div>

          <!-- Descrição -->
          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-sm text-stone-300">Descrição</label>
            <textarea
              v-model="form.description"
              class="input resize-none"
              rows="2"
              placeholder="Informações adicionais sobre a peça"
            />
          </div>

          <!-- Categoria -->
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Categoria</label>
            <input
              v-model="form.category"
              type="text"
              class="input"
              maxlength="100"
              placeholder="Ex: Tela, Bateria, Conector"
            />
          </div>

          <!-- SKU / Código -->
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">SKU / Código</label>
            <input
              v-model="form.sku"
              type="text"
              class="input font-mono"
              :class="{ 'border-red-500': errors.sku }"
              maxlength="100"
              placeholder="Ex: TEL-TELA-SAM-A54"
            />
            <p v-if="errors.sku" class="mt-1 text-xs text-red-400">{{ errors.sku }}</p>
          </div>

          <!-- Unidade -->
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Unidade de medida</label>
            <select v-model="form.unit" class="input">
              <option v-for="u in UNITS" :key="u" :value="u">{{ u }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Estoque -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="font-semibold text-white">Controle de Estoque</h3>
          <button
            v-if="isEdit"
            type="button"
            class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-medium text-stone-300 transition hover:border-amber-500/30 hover:text-amber-300"
            @click="showStockModal = true"
          >
            Ajustar estoque
          </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Quantidade atual</label>
            <input
              v-if="!isEdit"
              v-model.number="form.stock_quantity"
              type="number"
              class="input"
              min="0"
              step="0.001"
              placeholder="0"
            />
            <div v-else class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2">
              <span class="text-2xl font-bold tabular-nums text-white">{{ currentStock.toLocaleString('pt-BR') }}</span>
              <span class="text-sm text-stone-400">{{ form.unit }}</span>
            </div>
          </div>

          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Quantidade mínima (alerta)</label>
            <input
              v-model.number="form.min_stock_quantity"
              type="number"
              class="input"
              min="0"
              step="0.001"
              placeholder="0"
            />
          </div>
        </div>
      </div>

      <!-- Preços -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h3 class="mb-4 font-semibold text-white">Preços</h3>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Custo de aquisição (R$)</label>
            <input
              type="text"
              class="input font-mono"
              :value="costFormatted"
              inputmode="decimal"
              placeholder="0,00"
              @input="onCostInput"
            />
          </div>

          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Preço de venda sugerido (R$)</label>
            <input
              type="text"
              class="input font-mono"
              :value="saleFormatted"
              inputmode="decimal"
              placeholder="0,00"
              @input="onSaleInput"
            />
          </div>
        </div>
      </div>

      <!-- Status -->
      <label class="flex items-center gap-3 cursor-pointer">
        <input
          v-model="form.active"
          type="checkbox"
          class="h-4 w-4 rounded border border-white/20 bg-white/5 accent-amber-500"
        />
        <span class="text-sm font-medium text-stone-300">Peça ativa</span>
      </label>

      <!-- Ações -->
      <div class="flex gap-3 pt-2">
        <button
          type="submit"
          :disabled="saving"
          class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
        >
          {{ saving ? 'Salvando…' : 'Salvar Peça' }}
        </button>
        <RouterLink
          :to="tenantRoute('parts')"
          class="rounded-xl border border-white/10 px-6 py-2.5 text-sm text-stone-400 transition hover:text-stone-200"
        >
          Cancelar
        </RouterLink>
      </div>
    </form>
  </div>

  <!-- Modal: Ajuste de estoque -->
  <Teleport to="body">
    <div
      v-if="showStockModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
      @click.self="showStockModal = false"
    >
      <div class="w-full max-w-sm rounded-2xl border border-white/10 bg-stone-900 p-6">
        <h3 class="mb-4 text-lg font-semibold text-white">Ajustar Estoque</h3>

        <p class="mb-4 text-sm text-stone-300">
          Estoque atual: <strong class="tabular-nums text-white">{{ currentStock.toLocaleString('pt-BR') }} {{ form.unit }}</strong>
        </p>

        <div class="space-y-4">
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Quantidade (positivo para entrada, negativo para saída)</label>
            <input
              v-model.number="stockAdjustQty"
              type="number"
              step="0.001"
              class="input font-mono"
              placeholder="Ex: 10 ou -3"
              autofocus
            />
            <p class="mt-1 text-xs text-stone-400">
              Novo estoque: <strong class="text-stone-200">{{ (currentStock + (stockAdjustQty || 0)).toLocaleString('pt-BR') }} {{ form.unit }}</strong>
            </p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Observação (opcional)</label>
            <input
              v-model="stockAdjustNotes"
              type="text"
              class="input"
              maxlength="500"
              placeholder="Ex: Compra fornecedor X, nota 1234"
            />
          </div>

          <div class="flex gap-3 pt-2">
            <button
              class="flex-1 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
              :disabled="stockAdjusting || stockAdjustQty === 0"
              @click="doAdjustStock"
            >
              {{ stockAdjusting ? 'Salvando…' : 'Confirmar' }}
            </button>
            <button
              class="rounded-xl border border-white/10 px-4 py-2.5 text-sm text-stone-400 transition hover:text-stone-200"
              @click="showStockModal = false"
            >
              Cancelar
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
