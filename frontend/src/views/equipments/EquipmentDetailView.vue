<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import {
  getEquipment,
  uploadEquipmentEvidence,
  equipmentEvidenceDownloadUrl,
  type Equipment,
  type EquipmentEvidence,
} from '../../api/equipments'
import { useTenantRouting } from '../../composables/useTenantRouting'
import { ApiError } from '../../api/client'

const route = useRoute()
const { tenantRoute } = useTenantRouting()
const equipment = ref<Equipment | null>(null)
const loading = ref(true)

// Evidências
const showUploadForm = ref(false)
const uploadFile = ref<File | null>(null)
const uploadCaption = ref('')
const uploading = ref(false)
const uploadError = ref<string | null>(null)

const statusLabels: Record<string, { label: string; cls: string }> = {
  draft:            { label: 'Rascunho',         cls: 'bg-stone-500/20 text-stone-400' },
  open:             { label: 'Aberta',            cls: 'bg-amber-500/20 text-amber-300' },
  in_progress:      { label: 'Em Execução',       cls: 'bg-sky-500/20 text-sky-300' },
  done:             { label: 'Concluída',         cls: 'bg-emerald-500/20 text-emerald-300' },
  delivered:        { label: 'Entregue',          cls: 'bg-violet-500/20 text-violet-300' },
  cancelled:        { label: 'Cancelada',         cls: 'bg-red-500/20 text-red-300' },
  terminated:       { label: 'Encerrada',         cls: 'bg-zinc-500/20 text-zinc-400' },
  completed:        { label: 'Concluída',         cls: 'bg-emerald-500/20 text-emerald-300' },
  ready_for_pickup: { label: 'Aguard. Retirada',  cls: 'bg-violet-500/20 text-violet-300' },
  no_repair:        { label: 'Sem Reparo',        cls: 'bg-orange-500/20 text-orange-300' },
}

function formatDate(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function isImage(ev: EquipmentEvidence): boolean {
  return ev.mime_type.startsWith('image/')
}

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  uploadFile.value = input.files?.[0] ?? null
}

async function submitEvidence() {
  if (!equipment.value || !uploadFile.value) return
  uploading.value = true
  uploadError.value = null
  try {
    const res = await uploadEquipmentEvidence(
      equipment.value.id,
      uploadFile.value,
      uploadCaption.value || undefined,
    )
    equipment.value.evidences.unshift(res.data)
    showUploadForm.value = false
    uploadFile.value = null
    uploadCaption.value = ''
  } catch (e) {
    uploadError.value = e instanceof ApiError ? e.message : 'Erro ao enviar arquivo.'
  } finally {
    uploading.value = false
  }
}

onMounted(async () => {
  const res = await getEquipment(Number(route.params.id))
  equipment.value = res.data
  loading.value = false
})
</script>

<template>
  <div class="space-y-6">
    <RouterLink :to="tenantRoute('equipments')" class="text-stone-500 hover:text-stone-200">← Equipamentos</RouterLink>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

    <template v-else-if="equipment">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h1 class="text-2xl font-semibold text-white">{{ equipment.brand }} {{ equipment.model }}</h1>
          <p class="capitalize text-stone-400">{{ equipment.category }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <RouterLink
            :to="tenantRoute('service-orders.create', { query: { equipment_id: equipment.id } })"
            class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
          >
            + Nova OS
          </RouterLink>
          <RouterLink
            :to="tenantRoute('equipments.edit', { params: { id: equipment.id } })"
            class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400"
          >
            Editar
          </RouterLink>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Categoria</p>
          <p class="mt-2 text-lg font-semibold text-white capitalize">{{ equipment.category }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Cliente</p>
          <p class="mt-2 text-lg font-semibold text-white">{{ equipment.customer_name ?? '—' }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Histórico de série</p>
          <p class="mt-2 text-lg font-semibold text-white">{{ equipment.serial_overrides.length }}</p>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Identificação</h2>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-stone-500">Nº de Série</dt>
              <dd class="font-mono text-sm text-stone-200">{{ equipment.serial_number ?? '—' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-stone-500">Cliente</dt>
              <dd>
                <RouterLink
                  v-if="equipment.customer_id"
                  :to="tenantRoute('customers.detail', { params: { id: equipment.customer_id } })"
                  class="text-sm text-amber-400 hover:text-amber-300"
                >
                  {{ equipment.customer_name ?? equipment.customer_id }}
                </RouterLink>
                <span v-else class="text-sm text-stone-500">—</span>
              </dd>
            </div>
          </dl>
        </div>

        <div v-if="equipment.accessories || equipment.notes" class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Observações</h2>
          <p v-if="equipment.accessories" class="mb-2 text-sm text-stone-300">
            <span class="text-stone-500">Acessórios:</span> {{ equipment.accessories }}
          </p>
          <p v-if="equipment.notes" class="text-sm text-stone-300">{{ equipment.notes }}</p>
        </div>

        <div v-if="equipment.serial_overrides.length > 0" class="rounded-2xl border border-white/10 bg-white/5 p-6 lg:col-span-2">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Histórico de Série</h2>
          <div class="space-y-2">
            <div
              v-for="o in equipment.serial_overrides"
              :key="o.id"
              class="rounded-xl border border-white/5 bg-black/20 px-4 py-3"
            >
              <p class="font-mono text-sm text-stone-200">{{ o.serial_number }}</p>
              <p v-if="o.reason" class="text-xs text-stone-500">{{ o.reason }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Evidências Visuais -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
          <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-500">Evidências Visuais</h2>
          <button
            type="button"
            class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
            @click="showUploadForm = !showUploadForm"
          >
            {{ showUploadForm ? 'Cancelar' : 'Adicionar' }}
          </button>
        </div>

        <!-- Formulário de upload inline -->
        <div v-if="showUploadForm" class="mb-6 rounded-xl border border-white/10 bg-black/20 p-4 space-y-3">
          <div>
            <label class="mb-1 block text-xs font-semibold text-stone-400">Arquivo <span class="text-stone-600">(imagem ou PDF)</span></label>
            <input
              type="file"
              accept="image/*,application/pdf"
              class="block w-full text-sm text-stone-400 file:mr-3 file:rounded-lg file:border-0 file:bg-white/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-stone-200 hover:file:bg-white/20"
              @change="onFileChange"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold text-stone-400">Legenda <span class="text-stone-600">(opcional)</span></label>
            <textarea
              v-model="uploadCaption"
              rows="2"
              placeholder="Descreva a evidência…"
              class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-stone-200 placeholder-stone-600 outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/30 resize-none"
            />
          </div>
          <p v-if="uploadError" class="text-xs text-red-400">{{ uploadError }}</p>
          <button
            type="button"
            :disabled="!uploadFile || uploading"
            class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-40 disabled:cursor-not-allowed"
            @click="submitEvidence"
          >
            {{ uploading ? 'Enviando…' : 'Enviar' }}
          </button>
        </div>

        <!-- Galeria -->
        <div v-if="equipment.evidences.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="ev in equipment.evidences"
            :key="ev.id"
            class="rounded-xl border border-white/10 bg-black/20 overflow-hidden"
          >
            <!-- Miniatura para imagens -->
            <div v-if="isImage(ev)" class="aspect-video w-full overflow-hidden bg-black/30">
              <a
                :href="equipmentEvidenceDownloadUrl(equipment.id, ev.id)"
                target="_blank"
                rel="noopener noreferrer"
                class="block h-full w-full"
              >
                <img
                  :src="equipmentEvidenceDownloadUrl(equipment.id, ev.id)"
                  :alt="ev.caption ?? ev.file_name"
                  class="h-full w-full object-cover transition hover:opacity-80"
                />
              </a>
            </div>
            <!-- Ícone para outros arquivos -->
            <div v-else class="flex aspect-video items-center justify-center bg-black/30">
              <a
                :href="equipmentEvidenceDownloadUrl(equipment.id, ev.id)"
                target="_blank"
                rel="noopener noreferrer"
                class="flex flex-col items-center gap-2 text-stone-400 hover:text-amber-400 transition"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span class="text-xs">Abrir arquivo</span>
              </a>
            </div>
            <!-- Metadados -->
            <div class="p-3 space-y-1">
              <p class="truncate text-xs font-semibold text-stone-200">{{ ev.file_name }}</p>
              <p v-if="ev.caption" class="text-xs text-stone-400 line-clamp-2">{{ ev.caption }}</p>
              <div class="flex items-center justify-between gap-2 pt-1">
                <span class="text-xs text-stone-600">{{ formatDate(ev.created_at) }} · {{ formatBytes(ev.file_size_bytes) }}</span>
                <a
                  :href="equipmentEvidenceDownloadUrl(equipment.id, ev.id)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-xs text-amber-400 hover:text-amber-300 transition"
                >
                  Baixar
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Estado vazio -->
        <div v-else-if="!showUploadForm" class="py-8 text-center text-sm text-stone-600">
          Nenhuma evidência registrada.
        </div>
      </div>

      <!-- Histórico de Atendimentos -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Histórico de Atendimentos</h2>

        <div v-if="equipment.service_orders_history.length > 0" class="space-y-2">
          <RouterLink
            v-for="so in equipment.service_orders_history"
            :key="so.id"
            :to="tenantRoute('service-orders.detail', { params: { id: so.id } })"
            class="flex items-center gap-3 rounded-xl border border-white/5 bg-black/20 px-4 py-3 transition hover:border-white/10 hover:bg-black/30"
          >
            <span class="font-mono text-xs font-semibold text-amber-300 shrink-0">
              {{ so.order_number ?? `#${so.id}` }}
            </span>
            <span class="flex-1 truncate text-sm text-stone-200">{{ so.title ?? '—' }}</span>
            <span
              class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold"
              :class="statusLabels[so.status]?.cls ?? 'bg-stone-500/20 text-stone-400'"
            >
              {{ statusLabels[so.status]?.label ?? so.status }}
            </span>
            <span class="shrink-0 text-xs text-stone-600">{{ formatDate(so.opened_at) }}</span>
          </RouterLink>
        </div>

        <div v-else class="py-8 text-center text-sm text-stone-600">
          Nenhum atendimento registrado para este equipamento.
        </div>
      </div>
    </template>
  </div>
</template>
