<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import {
  getServiceOrder, getTimeline, getQuotes, updateDiagnosis,
  openDraft, startExecution, completeExecutionWithTests, markReadyForPickup, deliver,
  createQuote, recordPayment, cancelServiceOrder, closeNoRepair,
  generatePortalToken, recordWorkEntries, getFinancials, assignTechnician, getAttachments,
  deriveQuoteCharge, createWarrantyReturn, decideWarrantyCoverage,
  addDiagnosticFee, addAdjustment, trashServiceOrder,
  type ServiceOrder, type TimelineEvent, type QuoteRevision, type QuoteLineItem,
  type FinalTestResult, type WorkEntry, type Financials, type Attachment,
} from '../../api/serviceOrders'
import { listMemberships, type Membership } from '../../api/memberships'
import { API_BASE, ApiError } from '../../api/client'
import { parseMoneyCents } from '../../utils/masks'
import ServiceOrderCustomerEquipmentCard from './components/ServiceOrderCustomerEquipmentCard.vue'
import ServiceOrderDiagnosisCard from './components/ServiceOrderDiagnosisCard.vue'
import ServiceOrderCompleteExecutionForm from './components/ServiceOrderCompleteExecutionForm.vue'
import ServiceOrderPaymentForm from './components/ServiceOrderPaymentForm.vue'
import ServiceOrderQuotesSection from './components/ServiceOrderQuotesSection.vue'
import ServiceOrderTimelinePanel from './components/ServiceOrderTimelinePanel.vue'
import ServiceOrderWarrantyCard from './components/ServiceOrderWarrantyCard.vue'
import ServiceOrderWorkEntriesForm from './components/ServiceOrderWorkEntriesForm.vue'
import ServiceOrderQuoteForm from './components/ServiceOrderQuoteForm.vue'
import { useServiceOrderDerivedState } from './composables/useServiceOrderDerivedState'
import { useServiceOrderFormatting } from './composables/useServiceOrderFormatting'
import PasswordConfirmModal from '../../components/PasswordConfirmModal.vue'
import { useTenantRouting } from '../../composables/useTenantRouting'

const route = useRoute()
const router = useRouter()
const { tenantRoute } = useTenantRouting()
const os = ref<ServiceOrder | null>(null)
const timeline = ref<TimelineEvent[]>([])
const quotes = ref<QuoteRevision[]>([])
const financials = ref<Financials | null>(null)
const attachments = ref<Attachment[]>([])
const memberships = ref<Membership[]>([])
const loading = ref(true)
const actionError = ref('')
const actionLoading = ref(false)

// Diagnosis form
const showDiagnosisForm = ref(false)
const diagInternal = ref('')
const diagSummary = ref('')

// Quote form
const showQuoteForm = ref(false)
const quoteItems = ref<QuoteLineItem[]>([{ description: '', quantity: 1, unit_price_cents: 0 }])
const quoteNotes = ref('')
const quoteValidDays = ref(7)

// Payment form
const showPaymentForm = ref(false)
const paymentAmount = ref('')
const paymentMethod = ref('pix')
const paymentNotes = ref('')

// Cancel / no-repair modal
const showCancelModal = ref(false)
const showNoRepairModal = ref(false)
const terminalNotes = ref('')

// Deliver modal
const showDeliverModal = ref(false)
const deliverReceivedBy = ref('')
const deliverNotes = ref('')
const deliverAllowBalanceOverride = ref(false)
const deliverBalanceOverrideReason = ref('')

// Work entries form
const showWorkForm = ref(false)
const workServices = ref<WorkEntry[]>([{ description: '', quantity: 1, notes: null }])
const workParts = ref<WorkEntry[]>([])

// Final test results form
const showCompleteForm = ref(false)
const testResults = ref<FinalTestResult[]>([{ label: '', passed: true, notes: null }])

// Assignment
const selectedMembershipId = ref<number | null>(null)
const assignLoading = ref(false)

// Portal token
const portalLinks = ref<Record<number, string>>({})

// Charge forms
const showDiagFeeForm = ref(false)
const showAdjustForm = ref(false)
const chargeDesc = ref('')
const chargeAmount = ref('')
const chargeNotes = ref('')

// Delete (move to trash)
const showDeleteModal = ref(false)
const deleteError = ref('')
const deleteLoading = ref(false)

// Warranty
const showWarrantyReturnForm = ref(false)
const warrantyReturnProblem = ref('')
const showWarrantyDecisionForm = ref(false)
const warrantyDecision = ref<'covered' | 'not_covered'>('covered')
const warrantyDecisionNotes = ref('')

const {
  statusLabels,
  quoteStatusLabels,
  paymentMethodLabels,
  fmt,
  fmtMoney,
  fmtBytes,
  attachmentTypeLabel,
} = useServiceOrderFormatting()

const {
  signedServiceOrderCopyType,
  isTerminal,
  canOpenDraft,
  canDiagnose,
  canStartExec,
  canAddQuote,
  canCompleteExec,
  canReadyPick,
  canDeliver,
  canPay,
  canCancel,
  canAssign,
  canWorkEntries,
  canWarrantyReturn,
  isWarrantyReturn,
  canDecideWarranty,
  printableHref,
  quotePrintableHref,
  deliveryTermPrintableHref,
  hasSignedServiceOrderCopy,
} = useServiceOrderDerivedState(os, quotes, attachments, router)

const canTrash = computed(() =>
  os.value?.status === 'cancelled' || os.value?.status === 'no_repair',
)

const hasApprovedQuote = computed(() => quotes.value.some((quote) => quote.status === 'approved'))
const hasDerivedApprovedQuoteCharge = computed(() => (
  financials.value?.charges.some((charge) => charge.charge_type === 'approved_quote') ?? false
))

const startExecutionBlockers = computed(() => {
  if (os.value?.status !== 'open' || canStartExec.value) return []

  const blockers: string[] = []

  if (!os.value.diagnosis_completed_at) blockers.push('Registre o diagnóstico antes de iniciar a execução.')
  if (os.value.assigned_membership_id === null) blockers.push('Atribua um técnico responsável antes de iniciar a execução.')
  if (!hasApprovedQuote.value) blockers.push('É necessário ter ao menos um orçamento aprovado antes de iniciar a execução.')

  return blockers
})

const deliveryBlockers = computed(() => {
  if (os.value?.status !== 'ready_for_pickup') return []

  const blockers: string[] = []

  if (hasApprovedQuote.value && !hasDerivedApprovedQuoteCharge.value) {
    blockers.push('Derive a cobrança do orçamento aprovado antes de concluir a entrega.')
  }

  if ((financials.value?.summary.outstanding_balance_cents ?? 0) > 0) {
    blockers.push('Existe saldo pendente. Quite o valor ou use override de entrega se o seu perfil permitir.')
  }

  return blockers
})

async function reload() {
  const [osRes, tlRes, qtRes] = await Promise.all([
    getServiceOrder(Number(route.params.id)),
    getTimeline(Number(route.params.id)),
    getQuotes(Number(route.params.id)),
  ])
  os.value = osRes.data
  timeline.value = tlRes.data
  quotes.value = qtRes.data
  selectedMembershipId.value = osRes.data.assigned_membership_id

  if (!['draft', 'cancelled', 'no_repair'].includes(osRes.data.status)) {
    try {
      const finRes = await getFinancials(Number(route.params.id))
      financials.value = finRes.data
    } catch {
      // permission denied — not critical
    }
  }
}

async function loadAttachments() {
  try {
    const res = await getAttachments(Number(route.params.id))
    attachments.value = res.data
  } catch {
    // ignore
  }
}

onMounted(async () => {
  const [, , membershipsRes] = await Promise.all([
    reload(),
    loadAttachments(),
    listMemberships(),
  ])
  memberships.value = membershipsRes.data
  loading.value = false
})

async function doAction(fn: () => Promise<unknown>) {
  actionError.value = ''
  actionLoading.value = true
  try {
    await fn()
    await reload()
    await loadAttachments()
  } catch (e) {
    if (e instanceof ApiError) {
      const errData = e.data as { message?: string; errors?: Record<string, string[]> }
      const first = errData.errors ? Object.values(errData.errors)[0]?.[0] : null
      actionError.value = first ?? errData.message ?? 'Erro ao executar ação.'
    } else {
      actionError.value = 'Erro inesperado.'
    }
  } finally {
    actionLoading.value = false
  }
}

async function saveDiagnosis() {
  await doAction(() => updateDiagnosis(Number(route.params.id), diagInternal.value, diagSummary.value))
  showDiagnosisForm.value = false
}

async function doOpenDraft() {
  await doAction(() => openDraft(Number(route.params.id)))
}

async function doStartExec() {
  await doAction(() => startExecution(Number(route.params.id)))
}

async function doCompleteExec() {
  showCompleteForm.value = true
}

async function saveCompleteExec() {
  if (testResults.value.some(r => !r.label.trim())) {
    actionError.value = 'Todos os testes precisam ter um rótulo.'
    return
  }
  await doAction(() => completeExecutionWithTests(Number(route.params.id), testResults.value))
  showCompleteForm.value = false
  testResults.value = [{ label: '', passed: true, notes: null }]
}

async function doReadyPickup() {
  await doAction(() => markReadyForPickup(Number(route.params.id)))
}

async function doDeliver() {
  if (!deliverReceivedBy.value.trim()) return
  await doAction(() => deliver(Number(route.params.id), {
    received_by_name: deliverReceivedBy.value,
    notes: deliverNotes.value || undefined,
    allow_balance_override: deliverAllowBalanceOverride.value || undefined,
    balance_override_reason: deliverAllowBalanceOverride.value
      ? (deliverBalanceOverrideReason.value || undefined)
      : undefined,
  }))
  showDeliverModal.value = false
  deliverReceivedBy.value = ''
  deliverNotes.value = ''
  deliverAllowBalanceOverride.value = false
  deliverBalanceOverrideReason.value = ''
}

async function saveQuote() {
  const validUntil = new Date()
  validUntil.setDate(validUntil.getDate() + quoteValidDays.value)
  await doAction(() =>
    createQuote(Number(route.params.id), {
      line_items: quoteItems.value,
      notes: quoteNotes.value || null,
      valid_until: validUntil.toISOString().split('T')[0],
    }),
  )
  showQuoteForm.value = false
}

async function savePayment() {
  const cents = parseMoneyCents(paymentAmount.value)
  await doAction(() =>
    recordPayment(Number(route.params.id), {
      amount_cents: cents,
      payment_method: paymentMethod.value,
      notes: paymentNotes.value || undefined,
    }),
  )
  showPaymentForm.value = false
  paymentAmount.value = ''
  paymentNotes.value = ''
}

async function doCancel() {
  if (!terminalNotes.value.trim()) return
  await doAction(() => cancelServiceOrder(Number(route.params.id), terminalNotes.value))
  showCancelModal.value = false
  terminalNotes.value = ''
}

async function doNoRepair() {
  if (!terminalNotes.value.trim()) return
  await doAction(() => closeNoRepair(Number(route.params.id), terminalNotes.value))
  showNoRepairModal.value = false
  terminalNotes.value = ''
}

async function doAssign() {
  assignLoading.value = true
  try {
    await assignTechnician(Number(route.params.id), selectedMembershipId.value)
    await reload()
  } catch (e) {
    if (e instanceof ApiError) {
      actionError.value = (e.data as { message?: string })?.message ?? 'Erro ao atribuir técnico.'
    }
  } finally {
    assignLoading.value = false
  }
}

function copyLink(link: string) {
  navigator.clipboard.writeText(link).catch(() => {})
}

async function saveCharge(type: 'diagnostic_fee' | 'adjustment') {
  const cents = parseMoneyCents(chargeAmount.value)
  if (!chargeDesc.value.trim() || isNaN(cents) || cents === 0) {
    actionError.value = 'Preencha a descrição e o valor.'
    return
  }
  await doAction(() =>
    type === 'diagnostic_fee'
      ? addDiagnosticFee(Number(route.params.id), chargeDesc.value, cents, chargeNotes.value || undefined)
      : addAdjustment(Number(route.params.id), chargeDesc.value, cents, chargeNotes.value || undefined),
  )
  showDiagFeeForm.value = false
  showAdjustForm.value = false
  chargeDesc.value = ''
  chargeAmount.value = ''
  chargeNotes.value = ''
}

async function saveWarrantyReturn() {
  if (!warrantyReturnProblem.value.trim()) return
  await doAction(async () => {
    const res = await createWarrantyReturn(Number(route.params.id), warrantyReturnProblem.value)
    showWarrantyReturnForm.value = false
    warrantyReturnProblem.value = ''
    return res
  })
}

function openDeleteModal() {
  deleteError.value = ''
  showDeleteModal.value = true
}

async function doTrashServiceOrder(password: string) {
  deleteError.value = ''
  deleteLoading.value = true
  try {
    await trashServiceOrder(Number(route.params.id), password)
    router.push(tenantRoute('service-orders'))
  } catch (e) {
    if (e instanceof ApiError) {
      const errData = e.data as { message?: string; errors?: Record<string, string[]> }
      const first = errData.errors ? Object.values(errData.errors)[0]?.[0] : null
      deleteError.value = first ?? errData.message ?? 'Erro ao mover para lixeira.'
    } else {
      deleteError.value = 'Erro inesperado.'
    }
  } finally {
    deleteLoading.value = false
  }
}

async function saveWarrantyDecision() {
  if (!warrantyDecisionNotes.value.trim()) return
  await doAction(() => decideWarrantyCoverage(Number(route.params.id), warrantyDecision.value, warrantyDecisionNotes.value))
  showWarrantyDecisionForm.value = false
  warrantyDecisionNotes.value = ''
}

async function doGeneratePortalToken(quoteId: number) {
  try {
    const res = await generatePortalToken(Number(route.params.id), quoteId)
    const token = res.data.portal_token
    portalLinks.value[quoteId] = `${location.origin}/portal/${token}`
  } catch (e) {
    if (e instanceof ApiError) {
      actionError.value = (e.data as { message?: string })?.message ?? 'Erro ao gerar link.'
    }
  }
}

async function saveWorkEntries() {
  const services = workServices.value.filter(s => s.description.trim())
  const parts = workParts.value.filter(p => p.description.trim())
  if (!services.length && !parts.length) {
    actionError.value = 'Informe ao menos um serviço ou peça.'
    return
  }
  await doAction(() => recordWorkEntries(Number(route.params.id), {
    services: services.length ? services : undefined,
    parts: parts.length ? parts : undefined,
  }))
  showWorkForm.value = false
  workServices.value = [{ description: '', quantity: 1, notes: null }]
  workParts.value = []
}

async function doDeriveCharge() {
  await doAction(() => deriveQuoteCharge(Number(route.params.id)))
}

function addQuoteItem() {
  quoteItems.value.push({ description: '', quantity: 1, unit_price_cents: 0 })
}

function removeQuoteItem(i: number) {
  quoteItems.value.splice(i, 1)
}

const quoteTotal = computed(() =>
  quoteItems.value.reduce((sum, item) => sum + item.unit_price_cents * item.quantity, 0),
)

function openDiagnosisForm() {
  diagInternal.value = os.value?.diagnosis_internal_notes ?? ''
  diagSummary.value = os.value?.diagnosis_customer_summary ?? ''
  showDiagnosisForm.value = true
}

function openCancelModal() {
  terminalNotes.value = ''
  showCancelModal.value = true
}

function openNoRepairModal() {
  terminalNotes.value = ''
  showNoRepairModal.value = true
}

function openDeliverModal() {
  deliverReceivedBy.value = ''
  deliverNotes.value = ''
  deliverAllowBalanceOverride.value = false
  deliverBalanceOverrideReason.value = ''
  showDeliverModal.value = true
}

const uploadLoading = ref(false)
const uploadType = ref('intake_photo')
const showUploadForm = ref(false)
const uploadError = ref('')

async function uploadAttachment(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  uploadError.value = ''
  uploadLoading.value = true
  try {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('attachment_type', uploadType.value)
    const token = localStorage.getItem('access_token')
    const res = await fetch(`${API_BASE}/service-orders/${route.params.id}/attachments`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
      body: formData,
    })
    if (!res.ok) {
      const err = await res.json().catch(() => ({}))
      throw new Error((err as { message?: string }).message ?? 'Upload falhou.')
    }
    await loadAttachments()
    input.value = ''
    showUploadForm.value = false
  } catch (err) {
    uploadError.value = err instanceof Error ? err.message : 'Erro ao enviar arquivo.'
  } finally {
    uploadLoading.value = false
  }
}

async function downloadAttachment(attachmentId: number) {
  const token = localStorage.getItem('access_token')
  const url = `${API_BASE}/service-orders/${route.params.id}/attachments/${attachmentId}/download`
  try {
    const res = await fetch(url, { headers: { Authorization: `Bearer ${token}`, Accept: '*/*' } })
    if (!res.ok) throw new Error('Download falhou')
    const blob = await res.blob()
    const disposition = res.headers.get('content-disposition') ?? ''
    const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
    const filename = match ? match[1].replace(/['"]/g, '') : 'arquivo'
    const blobUrl = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = blobUrl
    anchor.download = filename
    anchor.click()
    URL.revokeObjectURL(blobUrl)
  } catch {
    actionError.value = 'Erro ao baixar arquivo.'
  }
}
</script>

<template>
  <div class="space-y-6">
    <RouterLink :to="tenantRoute('service-orders')" class="text-stone-500 hover:text-stone-200">← Ordens de Serviço</RouterLink>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

    <template v-else-if="os">
      <!-- Cabeçalho -->
      <div class="flex flex-wrap items-start gap-4">
        <div class="flex-1 min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-sm font-semibold text-amber-300">{{ os.order_number ?? os.draft_reference ?? os.reference }}</span>
            <span :class="statusLabels[os.status]?.cls" class="rounded-full px-2.5 py-0.5 text-xs font-semibold">
              {{ statusLabels[os.status]?.label ?? os.status }}
            </span>
          </div>
          <h1 class="mt-1 text-2xl font-semibold text-white">{{ os.title }}</h1>
        </div>
        <!-- Links de documentos -->
        <div class="flex flex-wrap items-center gap-2 pt-1">
          <a
            v-if="os.status !== 'draft'"
            :href="printableHref"
            target="_blank"
            rel="noopener noreferrer"
            class="link-doc"
          >
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 shrink-0">
              <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v2a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-2h1a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-1V4a1 1 0 0 0-1-1H6a1 1 0 0 0-1 1zm2 0h6v3H7V4zm-1 9v-2h8v2H6zm8-6H6V6h8v1z" clip-rule="evenodd" />
            </svg>
            Imprimir OS
          </a>
          <a
            v-if="quotes.length > 0"
            :href="quotePrintableHref"
            target="_blank"
            rel="noopener noreferrer"
            class="link-doc"
          >
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 shrink-0">
              <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0 1 12 2.586L15.414 6A2 2 0 0 1 16 7.414V16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4zm2 6a1 1 0 0 1 1-1h6a1 1 0 1 1 0 2H7a1 1 0 0 1-1-1zm1 3a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2H7z" clip-rule="evenodd" />
            </svg>
            Orçamento PDF
          </a>
          <a
            v-if="os.status === 'delivered'"
            :href="deliveryTermPrintableHref"
            target="_blank"
            rel="noopener noreferrer"
            class="link-doc"
          >
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 shrink-0">
              <path d="M9 2a1 1 0 0 0 0 2h2a1 1 0 1 0 0-2H9z" />
              <path fill-rule="evenodd" d="M4 5a2 2 0 0 1 2-2 3 3 0 0 0 3 3h2a3 3 0 0 0 3-3 2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5zm9.707 5.707a1 1 0 0 0-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            Termo de Entrega
          </a>
        </div>
      </div>

      <!-- Erro de ação -->
      <div v-if="actionError" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
        {{ actionError }}
      </div>

      <!-- Barra de ações: ação primária + ações de suporte + zona destrutiva -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <!-- Lado esquerdo: ação principal do momento + ações de suporte -->
        <div class="flex flex-wrap items-center gap-2">
          <!-- Ação primária: uma por status, visualmente destacada -->
          <button
            v-if="canOpenDraft"
            :disabled="actionLoading"
            class="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
            @click="doOpenDraft"
          >
            Abrir OS
          </button>
          <button
            v-else-if="canDiagnose && !os.diagnosis_completed_at"
            :disabled="actionLoading"
            class="rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-500 disabled:opacity-50"
            @click="openDiagnosisForm"
          >
            Registrar Diagnóstico
          </button>
          <button
            v-else-if="canStartExec"
            :disabled="actionLoading"
            class="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
            @click="doStartExec"
          >
            Iniciar Execução
          </button>
          <button
            v-else-if="canCompleteExec"
            :disabled="actionLoading"
            class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-50"
            @click="doCompleteExec"
          >
            Concluir Execução
          </button>
          <button
            v-else-if="canReadyPick"
            :disabled="actionLoading"
            class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-violet-500 disabled:opacity-50"
            @click="doReadyPickup"
          >
            Pronto para Retirada
          </button>
          <button
            v-else-if="canDeliver"
            :disabled="actionLoading"
            class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-50"
            @click="openDeliverModal"
          >
            Registrar Entrega
          </button>
          <button
            v-else-if="canDecideWarranty"
            :disabled="actionLoading"
            class="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
            @click="showWarrantyDecisionForm = true"
          >
            Decidir Cobertura de Garantia
          </button>

          <!-- Separador visual entre ação primária e ações de suporte -->
          <span
            v-if="(canDiagnose && os.diagnosis_completed_at) || canAddQuote || canWorkEntries || canPay || canWarrantyReturn"
            class="mx-1 h-5 w-px bg-white/10"
          />

          <!-- Ações de suporte: menores, sem preenchimento -->
          <button
            v-if="canDiagnose && os.diagnosis_completed_at"
            :disabled="actionLoading"
            class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400 transition hover:border-sky-500/30 hover:text-sky-300 disabled:opacity-50"
            @click="openDiagnosisForm"
          >
            Editar Diagnóstico
          </button>
          <button
            v-if="canAddQuote"
            :disabled="actionLoading"
            class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400 transition hover:border-amber-500/30 hover:text-amber-300 disabled:opacity-50"
            @click="showQuoteForm = true"
          >
            + Orçamento
          </button>
          <button
            v-if="canWorkEntries"
            :disabled="actionLoading"
            class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400 transition hover:border-amber-500/30 hover:text-amber-300 disabled:opacity-50"
            @click="showWorkForm = true"
          >
            + Serviço/Peça
          </button>
          <button
            v-if="canPay"
            :disabled="actionLoading"
            class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400 transition hover:border-emerald-500/30 hover:text-emerald-300 disabled:opacity-50"
            @click="showPaymentForm = true"
          >
            + Pagamento
          </button>
          <button
            v-if="canWarrantyReturn"
            :disabled="actionLoading"
            class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400 transition hover:border-violet-500/30 hover:text-violet-300 disabled:opacity-50"
            @click="showWarrantyReturnForm = true"
          >
            Abrir Retorno de Garantia
          </button>
        </div>

        <!-- Lado direito: ações de encerramento (destrutivas) -->
        <div v-if="canCancel || canTrash" class="flex items-center gap-2">
          <button
            v-if="canCancel"
            :disabled="actionLoading"
            class="rounded-xl border border-red-500/20 px-4 py-2 text-sm text-red-400 transition hover:bg-red-500/10 disabled:opacity-50"
            @click="openCancelModal"
          >
            Cancelar OS
          </button>
          <button
            v-if="canCancel"
            :disabled="actionLoading"
            class="rounded-xl border border-orange-500/20 px-4 py-2 text-sm text-orange-400 transition hover:bg-orange-500/10 disabled:opacity-50"
            @click="openNoRepairModal"
          >
            Sem Reparo
          </button>
          <button
            v-if="canTrash"
            :disabled="actionLoading"
            class="rounded-xl border border-red-800/40 px-4 py-2 text-sm text-red-500/80 transition hover:border-red-600/50 hover:bg-red-900/20 hover:text-red-400 disabled:opacity-50"
            @click="openDeleteModal"
          >
            Mover para Lixeira
          </button>
        </div>
      </div>

      <!-- Pendências: alertas compactos, não cards -->
      <div v-if="startExecutionBlockers.length || deliveryBlockers.length" class="space-y-2">
        <div
          v-if="startExecutionBlockers.length"
          class="flex items-start gap-3 rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3"
        >
          <span class="mt-0.5 shrink-0 text-amber-400 text-sm">⚠</span>
          <div>
            <p class="text-xs font-semibold text-amber-300">Pendências para iniciar execução</p>
            <ul class="mt-1 space-y-0.5 text-sm text-stone-400">
              <li v-for="blocker in startExecutionBlockers" :key="blocker">{{ blocker }}</li>
            </ul>
          </div>
        </div>
        <div
          v-if="deliveryBlockers.length"
          class="flex items-start gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-3"
        >
          <span class="mt-0.5 shrink-0 text-emerald-400 text-sm">⚠</span>
          <div>
            <p class="text-xs font-semibold text-emerald-300">Pendências para entrega</p>
            <ul class="mt-1 space-y-0.5 text-sm text-stone-400">
              <li v-for="blocker in deliveryBlockers" :key="blocker">{{ blocker }}</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Diagnóstico form -->
      <div v-if="showDiagnosisForm" class="rounded-2xl border border-sky-500/20 bg-sky-500/5 p-6">
        <h2 class="mb-4 text-sm font-semibold text-sky-300">Diagnóstico Técnico</h2>
        <div class="space-y-4">
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Notas internas (apenas para a equipe)</label>
            <textarea v-model="diagInternal" rows="3" class="input resize-none" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Resumo para o cliente</label>
            <textarea v-model="diagSummary" rows="3" class="input resize-none" />
          </div>
          <div class="flex gap-2">
            <button class="rounded-xl bg-sky-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-400" @click="saveDiagnosis">Salvar</button>
            <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="showDiagnosisForm = false">Cancelar</button>
          </div>
        </div>
      </div>

      <ServiceOrderQuoteForm
        v-if="showQuoteForm"
        :quote-items="quoteItems"
        :quote-notes="quoteNotes"
        :quote-valid-days="quoteValidDays"
        :quote-total="quoteTotal"
        :format-money="fmtMoney"
        @update-quote-notes="quoteNotes = $event"
        @update-quote-valid-days="quoteValidDays = $event"
        @add-item="addQuoteItem"
        @remove-item="removeQuoteItem"
        @update-item-description="(index, value) => { quoteItems[index].description = value }"
        @update-item-quantity="(index, value) => { quoteItems[index].quantity = value }"
        @update-item-unit-price-cents="(index, value) => { quoteItems[index].unit_price_cents = Number.isNaN(value) ? 0 : value }"
        @save="saveQuote"
        @cancel="showQuoteForm = false"
      />

      <ServiceOrderWorkEntriesForm
        v-if="showWorkForm"
        :work-services="workServices"
        :work-parts="workParts"
        @add-service="workServices.push({ description: '', quantity: 1, notes: null })"
        @remove-service="workServices.splice($event, 1)"
        @update-service-description="(index, value) => { workServices[index].description = value }"
        @update-service-quantity="(index, value) => { workServices[index].quantity = value }"
        @add-part="workParts.push({ description: '', quantity: 1, notes: null })"
        @remove-part="workParts.splice($event, 1)"
        @update-part-description="(index, value) => { workParts[index].description = value }"
        @update-part-quantity="(index, value) => { workParts[index].quantity = value }"
        @save="saveWorkEntries"
        @cancel="showWorkForm = false"
      />

      <ServiceOrderCompleteExecutionForm
        v-if="showCompleteForm"
        :test-results="testResults"
        @add-test="testResults.push({ label: '', passed: true, notes: null })"
        @remove-test="testResults.splice($event, 1)"
        @update-test-label="(index, value) => { testResults[index].label = value }"
        @update-test-passed="(index, value) => { testResults[index].passed = value }"
        @save="saveCompleteExec"
        @cancel="showCompleteForm = false"
      />

      <ServiceOrderPaymentForm
        v-if="showPaymentForm"
        :payment-amount="paymentAmount"
        :payment-method="paymentMethod"
        :payment-notes="paymentNotes"
        @update-payment-amount="paymentAmount = $event"
        @update-payment-method="paymentMethod = $event"
        @update-payment-notes="paymentNotes = $event"
        @save="savePayment"
        @cancel="showPaymentForm = false"
      />

      <!-- Modal entrega -->
      <div v-if="showDeliverModal" class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-6">
        <h2 class="mb-4 text-sm font-semibold text-emerald-300">Registrar Entrega</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Nome de quem está retirando *</label>
            <input v-model="deliverReceivedBy" class="input" placeholder="Nome completo" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Observações</label>
            <input v-model="deliverNotes" class="input" />
          </div>
        </div>
        <div
          v-if="(financials?.summary.outstanding_balance_cents ?? 0) > 0"
          class="mt-4 rounded-2xl border border-amber-500/20 bg-amber-500/5 p-4"
        >
          <label class="flex cursor-pointer items-start gap-3">
            <input
              v-model="deliverAllowBalanceOverride"
              type="checkbox"
              class="mt-1 h-4 w-4 rounded border-white/10 bg-stone-950 text-amber-500 focus:ring-amber-500"
            />
            <span class="space-y-1">
              <span class="block text-sm font-medium text-amber-200">Permitir entrega com saldo pendente</span>
              <span class="block text-xs text-stone-400">
                Saldo em aberto: {{ fmtMoney(financials?.summary.outstanding_balance_cents ?? 0) }}. A confirmação depende da permissão de override no backend.
              </span>
            </span>
          </label>

          <div v-if="deliverAllowBalanceOverride" class="mt-4">
            <label class="mb-1.5 block text-sm text-stone-300">Motivo do override *</label>
            <textarea
              v-model="deliverBalanceOverrideReason"
              rows="3"
              class="input resize-none"
              placeholder="Explique por que a entrega está sendo liberada com saldo pendente"
            />
          </div>
        </div>
        <div class="mt-4 flex gap-2">
          <button :disabled="!deliverReceivedBy.trim()" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-50" @click="doDeliver">Confirmar Entrega</button>
          <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="showDeliverModal = false">Cancelar</button>
        </div>
      </div>

      <!-- Modal cancelamento -->
      <div v-if="showCancelModal" class="rounded-2xl border border-red-500/20 bg-red-500/5 p-6">
        <h2 class="mb-2 text-sm font-semibold text-red-400">Cancelar Ordem de Serviço</h2>
        <p class="mb-4 text-sm text-stone-400">Essa ação é irreversível. Informe o motivo do cancelamento.</p>
        <textarea v-model="terminalNotes" rows="3" class="input resize-none" placeholder="Motivo do cancelamento…" />
        <div class="mt-4 flex gap-2">
          <button :disabled="!terminalNotes.trim()" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-50" @click="doCancel">Confirmar Cancelamento</button>
          <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="showCancelModal = false">Voltar</button>
        </div>
      </div>

      <!-- Modal sem reparo -->
      <div v-if="showNoRepairModal" class="rounded-2xl border border-orange-500/20 bg-orange-500/5 p-6">
        <h2 class="mb-2 text-sm font-semibold text-orange-400">Fechar como Sem Reparo</h2>
        <p class="mb-4 text-sm text-stone-400">Informe o motivo pelo qual o reparo não foi possível.</p>
        <textarea v-model="terminalNotes" rows="3" class="input resize-none" placeholder="Motivo (ex: peça indisponível, custo inviável…)" />
        <div class="mt-4 flex gap-2">
          <button :disabled="!terminalNotes.trim()" class="rounded-xl bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-500 disabled:opacity-50" @click="doNoRepair">Confirmar</button>
          <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="showNoRepairModal = false">Voltar</button>
        </div>
      </div>

      <div v-if="showWarrantyReturnForm" class="rounded-2xl border border-violet-500/20 bg-violet-500/5 p-6">
        <h2 class="mb-2 text-sm font-semibold text-violet-400">Abrir Retorno de Garantia</h2>
        <p class="mb-4 text-sm text-stone-400">Uma nova OS será criada vinculada a esta, com sinal automático de elegibilidade.</p>
        <div>
          <label class="mb-1.5 block text-sm text-stone-300">Problema relatado *</label>
          <textarea v-model="warrantyReturnProblem" rows="3" class="input resize-none" placeholder="Descreva o motivo do retorno…" />
        </div>
        <div class="mt-4 flex gap-2">
          <button :disabled="!warrantyReturnProblem.trim() || actionLoading" class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500 disabled:opacity-50" @click="saveWarrantyReturn">Abrir Retorno</button>
          <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="showWarrantyReturnForm = false">Cancelar</button>
        </div>
      </div>

      <div v-if="showWarrantyDecisionForm" class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-6">
        <h2 class="mb-4 text-sm font-semibold text-amber-300">Decisão de Cobertura de Garantia</h2>
        <div class="mb-4 flex gap-4">
          <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-300">
            <input v-model="warrantyDecision" type="radio" value="covered" class="accent-emerald-500" />
            <span class="text-emerald-400">Coberto pela garantia</span>
          </label>
          <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-300">
            <input v-model="warrantyDecision" type="radio" value="not_covered" class="accent-red-500" />
            <span class="text-red-400">Não coberto</span>
          </label>
        </div>
        <div>
          <label class="mb-1.5 block text-sm text-stone-300">Notas da decisão *</label>
          <textarea v-model="warrantyDecisionNotes" rows="3" class="input resize-none" placeholder="Justificativa da decisão de cobertura…" />
        </div>
        <div class="mt-4 flex gap-2">
          <button :disabled="!warrantyDecisionNotes.trim() || actionLoading" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-400 disabled:opacity-50" @click="saveWarrantyDecision">Confirmar Decisão</button>
          <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="showWarrantyDecisionForm = false">Cancelar</button>
        </div>
      </div>

      <!-- Layout principal: contexto (esquerda) + workflow (direita) -->
      <div class="grid gap-6 lg:grid-cols-[1fr_2fr]">

        <!-- COLUNA ESQUERDA: contexto fixo — quem, o quê, quem cuida, arquivos -->
        <div class="space-y-4">
          <ServiceOrderCustomerEquipmentCard :service-order="os" />

          <!-- Técnico Responsável -->
          <div v-if="canAssign || os.assigned_membership_id" class="rounded-2xl border border-white/10 bg-white/5 p-5">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-widest text-stone-500">Técnico Responsável</h2>
            <div v-if="canAssign" class="flex items-center gap-2">
              <select v-model="selectedMembershipId" class="input flex-1">
                <option :value="null">— Sem atribuição —</option>
                <option v-for="m in memberships" :key="m.id" :value="m.id">
                  {{ m.user_name ?? m.user_email }} ({{ m.role }})
                </option>
              </select>
              <button
                :disabled="assignLoading"
                class="rounded-xl bg-amber-500 px-3 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
                @click="doAssign"
              >
                {{ assignLoading ? '…' : 'Salvar' }}
              </button>
            </div>
            <div v-else-if="os.assigned_membership_name" class="text-sm text-stone-300">
              {{ os.assigned_membership_name }}
            </div>
            <div v-else class="text-sm text-stone-500">Nenhum técnico atribuído.</div>
          </div>

          <!-- Checklist de Recebimento -->
          <div v-if="os.intake_checklist && os.intake_checklist.length > 0" class="rounded-2xl border border-white/10 bg-white/5 p-5">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-widest text-stone-500">Checklist de Recebimento</h2>
            <div class="space-y-2">
              <div v-for="(item, i) in os.intake_checklist" :key="i" class="flex items-center gap-3 text-sm">
                <span
                  class="flex size-5 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                  :class="item.checked === true ? 'bg-emerald-500/20 text-emerald-400' : item.checked === false ? 'bg-red-500/20 text-red-400' : 'bg-stone-500/20 text-stone-400'"
                >
                  {{ item.checked === true ? '✓' : item.checked === false ? '✕' : '—' }}
                </span>
                <span class="text-stone-300">{{ item.label }}</span>
                <span v-if="item.notes" class="text-stone-500">· {{ item.notes }}</span>
              </div>
            </div>
          </div>

          <!-- Anexos -->
          <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
            <div class="mb-3 flex items-center justify-between">
              <h2 class="text-xs font-semibold uppercase tracking-widest text-stone-500">Anexos</h2>
              <button
                v-if="!isTerminal"
                class="rounded-lg border border-white/10 px-3 py-1 text-xs text-stone-400 transition hover:border-amber-500/30 hover:text-amber-300"
                :disabled="uploadLoading"
                @click="showUploadForm = !showUploadForm"
              >
                {{ uploadLoading ? 'Enviando…' : (showUploadForm ? 'Cancelar' : '+ Adicionar') }}
              </button>
            </div>
            <!-- Formulário de upload -->
            <div v-if="showUploadForm" class="mb-3 rounded-xl border border-white/10 bg-black/20 p-3 space-y-2">
              <div class="grid gap-2 grid-cols-[1fr_auto]">
                <select v-model="uploadType" class="input text-xs py-2">
                  <option value="intake_photo">Foto de Entrada</option>
                  <option value="diagnostic_image">Imagem Diagnóstico</option>
                  <option value="receipt">Recibo</option>
                  <option value="term">Termo</option>
                  <option :value="signedServiceOrderCopyType">Via Assinada da O.S.</option>
                  <option value="other">Outro</option>
                </select>
                <label class="cursor-pointer whitespace-nowrap rounded-xl border border-amber-500/30 px-3 py-2 text-xs text-amber-400 transition hover:bg-amber-500/10">
                  Escolher…
                  <input type="file" class="sr-only" :disabled="uploadLoading" @change="uploadAttachment" />
                </label>
              </div>
              <p v-if="uploadError" class="text-xs text-red-400">{{ uploadError }}</p>
            </div>
            <div
              v-if="hasSignedServiceOrderCopy"
              class="mb-3 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-300"
            >
              Via assinada da O.S. vinculada.
            </div>
            <div v-if="attachments.length === 0" class="text-sm text-stone-500">Nenhum anexo.</div>
            <div v-else class="space-y-1.5">
              <div
                v-for="a in attachments"
                :key="a.id"
                class="flex items-center justify-between rounded-lg border border-white/5 bg-black/20 px-3 py-2"
              >
                <div class="min-w-0">
                  <p class="truncate text-sm text-stone-200">{{ a.file_name }}</p>
                  <p class="text-xs text-stone-500">{{ attachmentTypeLabel(a.attachment_type) }} · {{ fmtBytes(a.file_size_bytes) }}</p>
                </div>
                <button class="ml-2 shrink-0 text-xs text-amber-400 hover:text-amber-300" @click="downloadAttachment(a.id)">
                  Baixar
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- COLUNA DIREITA: fluxo de trabalho — diagnóstico → orçamento → financeiro → execução -->
        <div class="space-y-4">
          <ServiceOrderWarrantyCard v-if="isWarrantyReturn" :service-order="os" />
          <ServiceOrderDiagnosisCard :service-order="os" :format-date-time="fmt" />

          <ServiceOrderQuotesSection
            :quotes="quotes"
            :portal-links="portalLinks"
            :quote-status-labels="quoteStatusLabels"
            :format-date-time="fmt"
            :format-money="fmtMoney"
            @generate-portal-token="doGeneratePortalToken"
            @copy-link="copyLink"
          />

          <!-- Financeiro -->
          <div v-if="financials" class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
              <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-500">Financeiro</h2>
              <div class="flex flex-wrap gap-2">
                <button
                  v-if="os.status === 'in_progress' && quotes.some(q => q.status === 'approved') && !financials.charges.some(c => c.charge_type === 'approved_quote')"
                  class="rounded-lg border border-amber-500/30 px-3 py-1 text-xs text-amber-400 hover:bg-amber-500/10"
                  @click="doDeriveCharge"
                >
                  Derivar Cobrança do Orçamento
                </button>
                <button
                  v-if="!isTerminal"
                  class="rounded-lg border border-white/10 px-3 py-1 text-xs text-stone-400 hover:border-amber-500/30 hover:text-amber-300"
                  @click="() => { showDiagFeeForm = !showDiagFeeForm; showAdjustForm = false }"
                >
                  + Taxa de Diagnóstico
                </button>
                <button
                  v-if="!isTerminal"
                  class="rounded-lg border border-white/10 px-3 py-1 text-xs text-stone-400 hover:border-amber-500/30 hover:text-amber-300"
                  @click="() => { showAdjustForm = !showAdjustForm; showDiagFeeForm = false }"
                >
                  + Ajuste
                </button>
              </div>
            </div>
            <!-- Formulário taxa diagnóstico -->
            <div v-if="showDiagFeeForm" class="mb-4 rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 space-y-3">
              <p class="text-xs font-semibold text-amber-300">Taxa de Diagnóstico</p>
              <div class="grid gap-3 sm:grid-cols-3">
                <input v-model="chargeDesc" placeholder="Descrição" class="input sm:col-span-2" />
                <input v-model="chargeAmount" placeholder="R$ (ex: 50,00)" class="input" />
              </div>
              <input v-model="chargeNotes" placeholder="Observações (opcional)" class="input" />
              <div class="flex gap-2">
                <button class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-stone-950 hover:bg-amber-400" @click="saveCharge('diagnostic_fee')">Salvar</button>
                <button class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-stone-400" @click="showDiagFeeForm = false">Cancelar</button>
              </div>
            </div>
            <!-- Formulário ajuste -->
            <div v-if="showAdjustForm" class="mb-4 rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 space-y-3">
              <p class="text-xs font-semibold text-amber-300">Ajuste Financeiro</p>
              <p class="text-xs text-stone-400">Use valor negativo para desconto (ex: -30,00).</p>
              <div class="grid gap-3 sm:grid-cols-3">
                <input v-model="chargeDesc" placeholder="Descrição" class="input sm:col-span-2" />
                <input v-model="chargeAmount" placeholder="R$ (ex: -30,00)" class="input" />
              </div>
              <input v-model="chargeNotes" placeholder="Observações (opcional)" class="input" />
              <div class="flex gap-2">
                <button class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-stone-950 hover:bg-amber-400" @click="saveCharge('adjustment')">Salvar</button>
                <button class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-stone-400" @click="showAdjustForm = false">Cancelar</button>
              </div>
            </div>
            <!-- Resumo -->
            <div class="mb-4 grid grid-cols-3 gap-3 rounded-xl border border-white/5 bg-black/20 p-4">
              <div>
                <p class="text-xs text-stone-500">Total Cobrado</p>
                <p class="text-lg font-semibold text-stone-200">{{ fmtMoney(financials.summary.total_charged_cents) }}</p>
              </div>
              <div>
                <p class="text-xs text-stone-500">Total Pago</p>
                <p class="text-lg font-semibold text-emerald-400">{{ fmtMoney(financials.summary.total_paid_cents) }}</p>
              </div>
              <div>
                <p class="text-xs text-stone-500">Saldo Devedor</p>
                <p
                  class="text-lg font-semibold"
                  :class="financials.summary.outstanding_balance_cents > 0 ? 'text-red-400' : 'text-stone-400'"
                >
                  {{ fmtMoney(financials.summary.outstanding_balance_cents) }}
                </p>
              </div>
            </div>
            <!-- Cobranças -->
            <div v-if="financials.charges.length > 0" class="mb-4">
              <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-stone-500">Cobranças</p>
              <div class="space-y-1.5">
                <div v-for="c in financials.charges" :key="c.id" class="flex justify-between text-sm">
                  <span class="text-stone-400">{{ c.description }}</span>
                  <span class="text-stone-300">{{ fmtMoney(c.amount_cents) }}</span>
                </div>
              </div>
            </div>
            <!-- Pagamentos -->
            <div v-if="financials.payments.length > 0">
              <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-stone-500">Pagamentos Recebidos</p>
              <div class="space-y-1.5">
                <div
                  v-for="p in financials.payments"
                  :key="p.id"
                  class="flex flex-wrap items-center justify-between gap-2 text-sm"
                >
                  <span class="text-stone-400">{{ paymentMethodLabels[p.payment_method] ?? p.payment_method }} — {{ fmt(p.received_at) }}</span>
                  <div class="flex items-center gap-3">
                    <a
                      :href="router.resolve(tenantRoute('service-orders.documents.receipt', { params: { id: route.params.id, paymentId: p.id } })).href"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-xs text-amber-400 hover:text-amber-300"
                    >
                      Imprimir recibo
                    </a>
                    <span class="text-emerald-400">{{ fmtMoney(p.amount_cents) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Serviços e Peças Registrados -->
          <div v-if="os.execution_entries && os.execution_entries.length > 0" class="rounded-2xl border border-white/10 bg-white/5 p-5">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-widest text-stone-500">Serviços e Peças Registrados</h2>
            <div class="space-y-1.5">
              <div v-for="entry in os.execution_entries" :key="entry.id" class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="entry.entry_type === 'service' ? 'bg-sky-500/20 text-sky-300' : 'bg-amber-500/20 text-amber-300'"
                  >
                    {{ entry.entry_type === 'service' ? 'Serviço' : 'Peça' }}
                  </span>
                  <span class="text-stone-300">{{ entry.description }}</span>
                </div>
                <span class="text-stone-500">{{ entry.quantity }}x</span>
              </div>
            </div>
          </div>

          <!-- Testes Finais -->
          <div v-if="os.final_test_results && os.final_test_results.length > 0" class="rounded-2xl border border-white/10 bg-white/5 p-5">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-widest text-stone-500">Testes Finais</h2>
            <div class="space-y-2">
              <div v-for="(test, i) in os.final_test_results" :key="i" class="flex items-center gap-3 text-sm">
                <span
                  class="flex size-5 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                  :class="test.passed ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'"
                >
                  {{ test.passed ? '✓' : '✕' }}
                </span>
                <span class="text-stone-300">{{ test.label }}</span>
                <span v-if="test.notes" class="text-stone-500">· {{ test.notes }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <ServiceOrderTimelinePanel :timeline="timeline" :format-date-time="fmt" />
    </template>
  </div>

  <PasswordConfirmModal
    v-if="showDeleteModal"
    title="Mover para a Lixeira"
    description="A OS será movida para a lixeira e poderá ser restaurada ou excluída definitivamente de lá."
    confirm-label="Mover para Lixeira"
    :loading="deleteLoading"
    :error="deleteError"
    @confirm="doTrashServiceOrder"
    @cancel="showDeleteModal = false"
  />
</template>

<style scoped>
@reference "../../style.css";

</style>
