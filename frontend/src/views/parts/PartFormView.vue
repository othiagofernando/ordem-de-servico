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
  <div class="mx-auto max-w-2xl space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <RouterLink :to="tenantRoute('parts')" class="text-sm hover:underline" style="color: var(--c-text-muted)">
        ← Peças e Estoque
      </RouterLink>
    </div>
    <h1 class="text-2xl font-semibold" style="color: var(--c-text)">
      {{ isEdit ? 'Editar Peça' : 'Nova Peça' }}
    </h1>

    <div v-if="loading" class="py-10 text-center text-sm" style="color: var(--c-text-dim)">Carregando...</div>

    <form v-else class="space-y-5" @submit.prevent="save">
      <!-- Nome -->
      <div>
        <label class="form-label">Nome *</label>
        <input v-model="form.name" type="text" class="form-input w-full" :class="{ 'border-red-500': errors.name }" maxlength="200" />
        <p v-if="errors.name" class="mt-1 text-xs text-red-400">{{ errors.name }}</p>
      </div>

      <!-- Descrição -->
      <div>
        <label class="form-label">Descrição</label>
        <textarea v-model="form.description" class="form-input w-full" rows="2" />
      </div>

      <!-- Categoria e SKU -->
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="form-label">Categoria</label>
          <input v-model="form.category" type="text" class="form-input w-full" maxlength="100" placeholder="Ex: Tela, Bateria, Conector..." />
        </div>
        <div>
          <label class="form-label">SKU / Código</label>
          <input v-model="form.sku" type="text" class="form-input w-full" :class="{ 'border-red-500': errors.sku }" maxlength="100" placeholder="Ex: TEL-TELA-SAM-A54" />
          <p v-if="errors.sku" class="mt-1 text-xs text-red-400">{{ errors.sku }}</p>
        </div>
      </div>

      <!-- Unidade -->
      <div>
        <label class="form-label">Unidade de medida</label>
        <select v-model="form.unit" class="form-input w-40">
          <option v-for="u in UNITS" :key="u" :value="u">{{ u }}</option>
        </select>
      </div>

      <!-- Estoque -->
      <div class="rounded-2xl border p-4 space-y-4" style="border-color: var(--c-border)">
        <div class="flex items-center justify-between">
          <h3 class="font-medium" style="color: var(--c-text)">Estoque</h3>
          <button
            v-if="isEdit"
            type="button"
            class="btn-ghost text-xs px-3 py-1.5"
            @click="showStockModal = true"
          >
            Ajustar estoque
          </button>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="form-label">Quantidade atual</label>
            <input
              v-if="!isEdit"
              v-model.number="form.stock_quantity"
              type="number"
              class="form-input w-full"
              min="0"
              step="0.001"
            />
            <div v-else class="flex items-center gap-2">
              <span class="text-2xl font-bold tabular-nums" style="color: var(--c-text)">{{ currentStock.toLocaleString('pt-BR') }}</span>
              <span class="text-sm" style="color: var(--c-text-dim)">{{ form.unit }}</span>
            </div>
          </div>
          <div>
            <label class="form-label">Quantidade mínima (alerta)</label>
            <input
              v-model.number="form.min_stock_quantity"
              type="number"
              class="form-input w-full"
              min="0"
              step="0.001"
            />
          </div>
        </div>
      </div>

      <!-- Preços -->
      <div class="rounded-2xl border p-4 space-y-4" style="border-color: var(--c-border)">
        <h3 class="font-medium" style="color: var(--c-text)">Preços</h3>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="form-label">Custo de aquisição (R$)</label>
            <input
              type="text"
              class="form-input w-full font-mono"
              :value="costFormatted"
              inputmode="decimal"
              @input="onCostInput"
            />
          </div>
          <div>
            <label class="form-label">Preço de venda sugerido (R$)</label>
            <input
              type="text"
              class="form-input w-full font-mono"
              :value="saleFormatted"
              inputmode="decimal"
              @input="onSaleInput"
            />
          </div>
        </div>
      </div>

      <!-- Ativo -->
      <label class="flex items-center gap-3 cursor-pointer">
        <input v-model="form.active" type="checkbox" class="h-4 w-4 rounded" />
        <span class="text-sm font-medium" style="color: var(--c-text)">Peça ativa</span>
      </label>

      <!-- Ações -->
      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn-primary" :disabled="saving">
          {{ saving ? 'Salvando...' : 'Salvar' }}
        </button>
        <RouterLink :to="tenantRoute('parts')" class="btn-ghost px-4 py-2 text-sm">
          Cancelar
        </RouterLink>
      </div>
    </form>
  </div>

  <!-- Modal ajuste de estoque -->
  <Teleport to="body">
    <div v-if="showStockModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="showStockModal = false">
      <div class="w-full max-w-sm rounded-2xl border p-6 space-y-4" style="background: var(--c-sidebar); border-color: var(--c-border)">
        <h3 class="text-lg font-semibold" style="color: var(--c-text)">Ajustar Estoque</h3>
        <p class="text-sm" style="color: var(--c-text-muted)">
          Estoque atual: <strong class="tabular-nums" style="color: var(--c-text)">{{ currentStock.toLocaleString('pt-BR') }} {{ form.unit }}</strong>
        </p>
        <div>
          <label class="form-label">Quantidade (positivo para entrada, negativo para saída)</label>
          <input
            v-model.number="stockAdjustQty"
            type="number"
            step="0.001"
            class="form-input w-full font-mono"
            placeholder="Ex: 10 ou -3"
          />
          <p class="mt-1 text-xs" style="color: var(--c-text-dim)">
            Novo estoque: <strong style="color: var(--c-text)">{{ (currentStock + (stockAdjustQty || 0)).toLocaleString('pt-BR') }} {{ form.unit }}</strong>
          </p>
        </div>
        <div>
          <label class="form-label">Observação (opcional)</label>
          <input v-model="stockAdjustNotes" type="text" class="form-input w-full" maxlength="500" placeholder="Ex: Compra fornecedor X, nota 1234" />
        </div>
        <div class="flex gap-3 pt-1">
          <button
            class="btn-primary flex-1"
            :disabled="stockAdjusting || stockAdjustQty === 0"
            @click="doAdjustStock"
          >
            {{ stockAdjusting ? 'Salvando...' : 'Confirmar' }}
          </button>
          <button class="btn-ghost px-4 py-2 text-sm" @click="showStockModal = false">Cancelar</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
