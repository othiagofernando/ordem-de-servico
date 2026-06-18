<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  listTrashedServiceOrders, restoreServiceOrder, forceDeleteServiceOrder,
  type TrashedServiceOrder,
} from '../../api/serviceOrders'
import { ApiError } from '../../api/client'
import PasswordConfirmModal from '../../components/PasswordConfirmModal.vue'
import { useTenantRouting } from '../../composables/useTenantRouting'

const { tenantRoute } = useTenantRouting()
const orders = ref<TrashedServiceOrder[]>([])
const total = ref(0)
const loading = ref(true)
const loadError = ref('')

type ModalAction = { type: 'restore' | 'force'; id: number }
const activeModal = ref<ModalAction | null>(null)
const modalLoading = ref(false)
const modalError = ref('')

const statusLabels: Record<string, { label: string; cls: string }> = {
  cancelled: { label: 'Cancelada', cls: 'bg-red-500/15 text-red-400' },
  no_repair: { label: 'Sem Reparo', cls: 'bg-orange-500/15 text-orange-400' },
}

function fmt(iso: string) {
  return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const res = await listTrashedServiceOrders()
    orders.value = res.data
    total.value = res.meta.total
  } catch {
    loadError.value = 'Erro ao carregar lixeira.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

function openModal(type: 'restore' | 'force', id: number) {
  activeModal.value = { type, id }
  modalError.value = ''
}

function closeModal() {
  activeModal.value = null
  modalError.value = ''
}

async function handleConfirm(password: string) {
  if (!activeModal.value) return
  modalError.value = ''
  modalLoading.value = true
  try {
    if (activeModal.value.type === 'restore') {
      await restoreServiceOrder(activeModal.value.id, password)
    } else {
      await forceDeleteServiceOrder(activeModal.value.id, password)
    }
    activeModal.value = null
    await load()
  } catch (e) {
    if (e instanceof ApiError) {
      const errData = e.data as { message?: string; errors?: Record<string, string[]> }
      const first = errData.errors ? Object.values(errData.errors)[0]?.[0] : null
      modalError.value = first ?? errData.message ?? 'Erro ao executar ação.'
    } else {
      modalError.value = 'Erro inesperado.'
    }
  } finally {
    modalLoading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <RouterLink :to="tenantRoute('service-orders')" class="text-stone-500 hover:text-stone-200">← Ordens de Serviço</RouterLink>
    </div>

    <div>
      <h1 class="text-2xl font-semibold text-white">Lixeira</h1>
      <p class="mt-1 text-sm text-stone-500">Ordens de serviço movidas para a lixeira. Restaure ou exclua definitivamente.</p>
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

    <div v-else-if="loadError" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ loadError }}
    </div>

    <div v-else-if="orders.length === 0" class="py-16 text-center text-stone-500">
      Nenhuma ordem na lixeira.
    </div>

    <div v-else class="space-y-3">
      <p class="text-sm text-stone-500">{{ total }} ordem(ns) na lixeira</p>

      <div
        v-for="order in orders"
        :key="order.id"
        class="rounded-2xl border border-white/8 bg-white/3 p-5"
      >
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-mono text-sm font-semibold text-stone-400">{{ order.reference }}</span>
              <span :class="statusLabels[order.status]?.cls" class="rounded-full px-2.5 py-0.5 text-xs font-semibold">
                {{ statusLabels[order.status]?.label ?? order.status }}
              </span>
            </div>
            <p class="mt-0.5 text-base font-medium text-white">{{ order.title }}</p>
            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-sm text-stone-500">
              <span v-if="order.customer_name">{{ order.customer_name }}</span>
              <span v-if="order.equipment_brand || order.equipment_model">
                {{ [order.equipment_brand, order.equipment_model].filter(Boolean).join(' ') }}
                <span v-if="order.equipment_serial_number" class="font-mono text-xs"> · {{ order.equipment_serial_number }}</span>
              </span>
              <span>Excluída em {{ fmt(order.deleted_at) }}</span>
            </div>
          </div>

          <div class="flex shrink-0 items-center gap-2">
            <button
              class="rounded-xl border border-emerald-500/30 px-4 py-2 text-sm text-emerald-400 transition hover:bg-emerald-500/10"
              @click="openModal('restore', order.id)"
            >
              Restaurar
            </button>
            <button
              class="rounded-xl border border-red-800/40 px-4 py-2 text-sm text-red-500/70 transition hover:border-red-600/50 hover:bg-red-900/20 hover:text-red-400"
              @click="openModal('force', order.id)"
            >
              Excluir Definitivamente
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <PasswordConfirmModal
    v-if="activeModal?.type === 'restore'"
    title="Restaurar Ordem de Serviço"
    description="A OS voltará para a lista e poderá ser consultada normalmente."
    confirm-label="Restaurar"
    :loading="modalLoading"
    :error="modalError"
    @confirm="handleConfirm"
    @cancel="closeModal"
  />

  <PasswordConfirmModal
    v-if="activeModal?.type === 'force'"
    title="Excluir Definitivamente"
    description="Todos os dados desta OS (histórico, orçamentos, pagamentos, anexos) serão removidos permanentemente. Esta ação é irreversível."
    confirm-label="Excluir Definitivamente"
    :loading="modalLoading"
    :error="modalError"
    @confirm="handleConfirm"
    @cancel="closeModal"
  />
</template>
