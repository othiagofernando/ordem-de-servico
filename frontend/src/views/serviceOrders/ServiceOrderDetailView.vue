<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import {
  getServiceOrder, getTimeline, getQuotes, updateDiagnosis,
  openDraft, startExecution, completeExecutionWithTests, markReadyForPickup, deliver,
  createQuote, recordPayment, cancelServiceOrder, closeNoRepair,
  generatePortalToken, recordWorkEntries, getFinancials, assignTechnician, getAttachments,
  deriveQuoteCharge, createWarrantyReturn, decideWarrantyCoverage,
  addDiagnosticFee, addAdjustment,
  type ServiceOrder, type TimelineEvent, type QuoteRevision, type QuoteLineItem,
  type FinalTestResult, type WorkEntry, type Financials, type Attachment,
} from '../../api/serviceOrders'
import { listMemberships, type Membership } from '../../api/memberships'
import { API_BASE } from '../../api/client'
import { ApiError } from '../../api/client'

const route = useRoute()
const router = useRouter()
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

// Warranty
const showWarrantyReturnForm = ref(false)
const warrantyReturnProblem = ref('')
const showWarrantyDecisionForm = ref(false)
const warrantyDecision = ref<'covered' | 'not_covered'>('covered')
const warrantyDecisionNotes = ref('')

const statusLabels: Record<string, { label: string; cls: string }> = {
  draft:            { label: 'Rascunho',         cls: 'bg-stone-500/20 text-stone-400' },
  open:             { label: 'Aberta',            cls: 'bg-sky-500/20 text-sky-300' },
  in_progress:      { label: 'Em Execução',       cls: 'bg-amber-500/20 text-amber-300' },
  completed:        { label: 'Concluída',         cls: 'bg-emerald-500/20 text-emerald-300' },
  ready_for_pickup: { label: 'Aguard. Retirada',  cls: 'bg-violet-500/20 text-violet-300' },
  delivered:        { label: 'Entregue',          cls: 'bg-emerald-600/20 text-emerald-400' },
  cancelled:        { label: 'Cancelada',         cls: 'bg-red-500/20 text-red-400' },
  no_repair:        { label: 'Sem Reparo',        cls: 'bg-orange-500/20 text-orange-300' },
}

const quoteStatusLabels: Record<string, { label: string; cls: string }> = {
  pending:  { label: 'Aguardando',  cls: 'bg-amber-500/20 text-amber-300' },
  approved: { label: 'Aprovado',    cls: 'bg-emerald-500/20 text-emerald-300' },
  rejected: { label: 'Recusado',    cls: 'bg-red-500/20 text-red-400' },
  expired:  { label: 'Expirado',    cls: 'bg-stone-500/20 text-stone-400' },
}

const paymentMethodLabels: Record<string, string> = {
  pix: 'PIX',
  cash: 'Dinheiro',
  credit_card: 'Cartão de Crédito',
  debit_card: 'Cartão de Débito',
  bank_transfer: 'Transferência',
  other: 'Outro',
}

function fmt(dt: string | null) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

function fmtMoney(cents: number) {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function fmtBytes(bytes: number) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

const isTerminal = computed(() => ['cancelled', 'no_repair', 'delivered'].includes(os.value?.status ?? ''))

const canOpenDraft    = computed(() => os.value?.status === 'draft')
const canDiagnose     = computed(() => os.value?.status === 'open')
const canStartExec    = computed(() => os.value?.status === 'open' && !!os.value?.diagnosis_completed_at)
const canAddQuote     = computed(() => ['open', 'in_progress'].includes(os.value?.status ?? ''))
const canCompleteExec = computed(() => os.value?.status === 'in_progress')
const canReadyPick    = computed(() => os.value?.status === 'completed')
const canDeliver      = computed(() => os.value?.status === 'ready_for_pickup')
const canPay          = computed(() => !['draft', 'cancelled', 'no_repair'].includes(os.value?.status ?? ''))
const canCancel       = computed(() => !isTerminal.value && os.value?.status !== undefined)
const canAssign       = computed(() => ['open', 'in_progress'].includes(os.value?.status ?? ''))
const canWorkEntries  = computed(() => os.value?.status === 'in_progress')
const canWarrantyReturn = computed(() => os.value?.status === 'delivered' && !os.value?.warranty_origin_service_order_id)
const isWarrantyReturn  = computed(() => !!os.value?.warranty_origin_service_order_id)
const canDecideWarranty = computed(() => isWarrantyReturn.value && os.value?.warranty_decision_status === 'pending')
const printableHref = computed(() => (
  os.value && os.value.status !== 'draft'
    ? router.resolve({ name: 'service-orders.print', params: { id: os.value.id } }).href
    : '#'
))
const quotePrintableHref = computed(() => (
  os.value && quotes.value.length > 0
    ? router.resolve({ name: 'service-orders.documents.quote', params: { id: os.value.id } }).href
    : '#'
))
const deliveryTermPrintableHref = computed(() => (
  os.value?.status === 'delivered'
    ? router.resolve({ name: 'service-orders.documents.delivery', params: { id: os.value.id } }).href
    : '#'
))

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
  await doAction(() => deliver(Number(route.params.id), deliverReceivedBy.value, deliverNotes.value || undefined))
  showDeliverModal.value = false
  deliverReceivedBy.value = ''
  deliverNotes.value = ''
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
  const cents = Math.round(parseFloat(paymentAmount.value.replace(',', '.')) * 100)
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
  const cents = Math.round(parseFloat(chargeAmount.value.replace(',', '.')) * 100)
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

const uploadLoading = ref(false)
const uploadType = ref('intake_photo')
const showUploadForm = ref(false)
const uploadError = ref('')
const signedServiceOrderCopyType = 'signed_service_order_copy'

const attachmentTypeLabels: Record<string, string> = {
  intake_photo: 'Foto de Entrada',
  diagnostic_image: 'Imagem Diagnóstico',
  receipt: 'Recibo',
  term: 'Termo',
  signed_service_order_copy: 'Via Assinada da O.S.',
  other: 'Outro',
}

function attachmentTypeLabel(type: string) {
  return attachmentTypeLabels[type] ?? type
}

const hasSignedServiceOrderCopy = computed(() =>
  attachments.value.some((attachment) => attachment.attachment_type === signedServiceOrderCopyType),
)

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
    <RouterLink :to="{ name: 'service-orders' }" class="text-stone-500 hover:text-stone-200">← Ordens de Serviço</RouterLink>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

    <template v-else-if="os">
      <!-- Cabeçalho -->
      <div class="flex flex-wrap items-start gap-4">
        <div class="flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-sm font-semibold text-amber-300">{{ os.order_number ?? os.draft_reference ?? os.reference }}</span>
            <span :class="statusLabels[os.status]?.cls" class="rounded-full px-2.5 py-0.5 text-xs font-semibold">
              {{ statusLabels[os.status]?.label ?? os.status }}
            </span>
          </div>
          <h1 class="mt-1 text-2xl font-semibold text-white">{{ os.title }}</h1>
        </div>
      </div>

      <!-- Erro de ação -->
      <div v-if="actionError" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
        {{ actionError }}
      </div>

      <!-- Ações primárias -->
      <div class="flex flex-wrap gap-2">
        <a
          v-if="os.status !== 'draft'"
          :href="printableHref"
          target="_blank"
          rel="noopener noreferrer"
          class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-300 transition hover:border-amber-500/30 hover:text-amber-300"
        >
          Imprimir OS
        </a>
        <a
          v-if="quotes.length > 0"
          :href="quotePrintableHref"
          target="_blank"
          rel="noopener noreferrer"
          class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-300 transition hover:border-amber-500/30 hover:text-amber-300"
        >
          Imprimir Orçamento
        </a>
        <a
          v-if="os.status === 'delivered'"
          :href="deliveryTermPrintableHref"
          target="_blank"
          rel="noopener noreferrer"
          class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-300 transition hover:border-amber-500/30 hover:text-amber-300"
        >
          Imprimir Termo de Entrega
        </a>
        <button
          v-if="canOpenDraft"
          :disabled="actionLoading"
          class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
          @click="doOpenDraft"
        >
          Abrir OS
        </button>
        <button
          v-if="canDiagnose"
          :disabled="actionLoading"
          class="rounded-xl border border-sky-500/30 bg-sky-500/10 px-4 py-2 text-sm font-semibold text-sky-300 transition hover:bg-sky-500/20 disabled:opacity-50"
          @click="openDiagnosisForm"
        >
          {{ os.diagnosis_completed_at ? 'Editar Diagnóstico' : 'Registrar Diagnóstico' }}
        </button>
        <button
          v-if="canStartExec"
          :disabled="actionLoading"
          class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-2 text-sm font-semibold text-amber-300 transition hover:bg-amber-500/20 disabled:opacity-50"
          @click="doStartExec"
        >
          Iniciar Execução
        </button>
        <button
          v-if="canAddQuote"
          :disabled="actionLoading"
          class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-300 transition hover:border-amber-500/30 hover:text-amber-300 disabled:opacity-50"
          @click="showQuoteForm = true"
        >
          + Orçamento
        </button>
        <button
          v-if="canWorkEntries"
          :disabled="actionLoading"
          class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-300 transition hover:border-amber-500/30 hover:text-amber-300 disabled:opacity-50"
          @click="showWorkForm = true"
        >
          + Serviço/Peça
        </button>
        <button
          v-if="canCompleteExec"
          :disabled="actionLoading"
          class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300 transition hover:bg-emerald-500/20 disabled:opacity-50"
          @click="doCompleteExec"
        >
          Concluir Execução
        </button>
        <button
          v-if="canReadyPick"
          :disabled="actionLoading"
          class="rounded-xl border border-violet-500/30 bg-violet-500/10 px-4 py-2 text-sm font-semibold text-violet-300 transition hover:bg-violet-500/20 disabled:opacity-50"
          @click="doReadyPickup"
        >
          Pronto para Retirada
        </button>
        <button
          v-if="canDeliver"
          :disabled="actionLoading"
          class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-50"
          @click="showDeliverModal = true"
        >
          Registrar Entrega
        </button>
        <button
          v-if="canPay"
          :disabled="actionLoading"
          class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-300 transition hover:border-emerald-500/30 hover:text-emerald-300 disabled:opacity-50"
          @click="showPaymentForm = true"
        >
          + Pagamento
        </button>
        <template v-if="canCancel">
          <button
            :disabled="actionLoading"
            class="rounded-xl border border-red-500/20 px-4 py-2 text-sm text-red-400 transition hover:bg-red-500/10 disabled:opacity-50"
            @click="openCancelModal"
          >
            Cancelar OS
          </button>
          <button
            :disabled="actionLoading"
            class="rounded-xl border border-orange-500/20 px-4 py-2 text-sm text-orange-400 transition hover:bg-orange-500/10 disabled:opacity-50"
            @click="openNoRepairModal"
          >
            Sem Reparo
          </button>
        </template>
        <button
          v-if="canWarrantyReturn"
          :disabled="actionLoading"
          class="rounded-xl border border-violet-500/20 px-4 py-2 text-sm text-violet-400 transition hover:bg-violet-500/10 disabled:opacity-50"
          @click="showWarrantyReturnForm = true"
        >
          Abrir Retorno de Garantia
        </button>
        <button
          v-if="canDecideWarranty"
          :disabled="actionLoading"
          class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-2 text-sm font-semibold text-amber-300 transition hover:bg-amber-500/20 disabled:opacity-50"
          @click="showWarrantyDecisionForm = true"
        >
          Decidir Cobertura de Garantia
        </button>
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

      <!-- Orçamento form -->
      <div v-if="showQuoteForm" class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-6">
        <h2 class="mb-4 text-sm font-semibold text-amber-300">Novo Orçamento</h2>
        <div class="space-y-3">
          <div
            v-for="(item, i) in quoteItems"
            :key="i"
            class="grid gap-2 sm:grid-cols-[1fr_80px_110px_auto]"
          >
            <input v-model="item.description" placeholder="Descrição do item" class="input" />
            <input v-model.number="item.quantity" type="number" min="1" placeholder="Qtd" class="input" />
            <input
              :value="(item.unit_price_cents / 100).toFixed(2)"
              type="number"
              min="0"
              step="0.01"
              placeholder="R$ unit."
              class="input"
              @change="(e) => { item.unit_price_cents = Math.round(parseFloat((e.target as HTMLInputElement).value) * 100) }"
            />
            <button v-if="quoteItems.length > 1" type="button" class="text-red-400 hover:text-red-300" @click="removeQuoteItem(i)">✕</button>
          </div>
          <button type="button" class="text-sm text-amber-400 hover:text-amber-300" @click="addQuoteItem">+ Adicionar item</button>

          <div class="flex items-center justify-between rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-2.5">
            <span class="text-sm text-stone-300">Total</span>
            <span class="font-semibold text-amber-300">{{ fmtMoney(quoteTotal) }}</span>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">Validade (dias)</label>
              <input v-model.number="quoteValidDays" type="number" min="1" class="input" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm text-stone-300">Observações</label>
              <input v-model="quoteNotes" class="input" />
            </div>
          </div>

          <div class="flex gap-2">
            <button class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-400" @click="saveQuote">Salvar Orçamento</button>
            <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="showQuoteForm = false">Cancelar</button>
          </div>
        </div>
      </div>

      <!-- Work entries form -->
      <div v-if="showWorkForm" class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-6">
        <h2 class="mb-4 text-sm font-semibold text-amber-300">Registrar Serviços e Peças</h2>
        <div class="space-y-4">
          <div>
            <div class="mb-2 flex items-center justify-between">
              <p class="text-xs font-semibold uppercase tracking-widest text-stone-400">Serviços</p>
              <button type="button" class="text-xs text-amber-400 hover:text-amber-300" @click="workServices.push({ description: '', quantity: 1, notes: null })">+ Adicionar</button>
            </div>
            <div class="space-y-2">
              <div v-for="(s, i) in workServices" :key="`svc-${i}`" class="flex items-center gap-2">
                <input v-model="s.description" placeholder="Descrição do serviço" class="input flex-1" />
                <input v-model.number="s.quantity" type="number" min="1" class="input w-20" />
                <button v-if="workServices.length > 1" type="button" class="text-red-400 hover:text-red-300" @click="workServices.splice(i, 1)">✕</button>
              </div>
            </div>
          </div>
          <div>
            <div class="mb-2 flex items-center justify-between">
              <p class="text-xs font-semibold uppercase tracking-widest text-stone-400">Peças</p>
              <button type="button" class="text-xs text-amber-400 hover:text-amber-300" @click="workParts.push({ description: '', quantity: 1, notes: null })">+ Adicionar</button>
            </div>
            <div class="space-y-2">
              <div v-for="(p, i) in workParts" :key="`part-${i}`" class="flex items-center gap-2">
                <input v-model="p.description" placeholder="Descrição da peça" class="input flex-1" />
                <input v-model.number="p.quantity" type="number" min="1" class="input w-20" />
                <button type="button" class="text-red-400 hover:text-red-300" @click="workParts.splice(i, 1)">✕</button>
              </div>
            </div>
          </div>
          <div class="flex gap-2">
            <button class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-400" @click="saveWorkEntries">Salvar</button>
            <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="showWorkForm = false">Cancelar</button>
          </div>
        </div>
      </div>

      <!-- Concluir execução form (testes finais) -->
      <div v-if="showCompleteForm" class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-6">
        <h2 class="mb-4 text-sm font-semibold text-emerald-300">Concluir Execução — Testes Finais</h2>
        <p class="mb-4 text-sm text-stone-400">Registre os resultados dos testes realizados antes de marcar como tecnicamente concluída.</p>
        <div class="space-y-2">
          <div v-for="(r, i) in testResults" :key="i" class="flex items-center gap-2">
            <input v-model="r.label" placeholder="Descrição do teste (ex: Liga normalmente)" class="input flex-1" />
            <select v-model="r.passed" class="input w-32">
              <option :value="true">Passou</option>
              <option :value="false">Falhou</option>
            </select>
            <button v-if="testResults.length > 1" type="button" class="text-red-400 hover:text-red-300" @click="testResults.splice(i, 1)">✕</button>
          </div>
        </div>
        <button type="button" class="mt-2 text-sm text-emerald-400 hover:text-emerald-300" @click="testResults.push({ label: '', passed: true, notes: null })">+ Adicionar teste</button>
        <div class="mt-4 flex gap-2">
          <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500" @click="saveCompleteExec">Concluir</button>
          <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="showCompleteForm = false">Cancelar</button>
        </div>
      </div>

      <!-- Pagamento form -->
      <div v-if="showPaymentForm" class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-6">
        <h2 class="mb-4 text-sm font-semibold text-emerald-300">Registrar Pagamento</h2>
        <div class="grid gap-4 sm:grid-cols-3">
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Valor (R$)</label>
            <input v-model="paymentAmount" type="text" placeholder="0,00" class="input" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Forma de Pagamento</label>
            <select v-model="paymentMethod" class="input">
              <option value="pix">PIX</option>
              <option value="cash">Dinheiro</option>
              <option value="credit_card">Cartão de Crédito</option>
              <option value="debit_card">Cartão de Débito</option>
              <option value="bank_transfer">Transferência</option>
              <option value="other">Outro</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Observações</label>
            <input v-model="paymentNotes" class="input" />
          </div>
        </div>
        <div class="mt-3 flex gap-2">
          <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500" @click="savePayment">Confirmar</button>
          <button class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-400" @click="showPaymentForm = false">Cancelar</button>
        </div>
      </div>

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

      <!-- Modal retorno de garantia -->
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

      <!-- Modal decisão de garantia -->
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

      <div class="grid gap-4 lg:grid-cols-2">
        <!-- Cliente e equipamento -->
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Cliente &amp; Equipamento</h2>
          <dl class="space-y-2.5">
            <div class="flex justify-between">
              <dt class="text-sm text-stone-500">Cliente</dt>
              <dd>
                <RouterLink
                  v-if="os.customer_id"
                  :to="{ name: 'customers.detail', params: { id: os.customer_id } }"
                  class="text-sm text-amber-400 hover:text-amber-300"
                >
                  {{ os.customer_name }}
                </RouterLink>
                <span v-else class="text-sm text-stone-500">—</span>
              </dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-stone-500">Contato</dt>
              <dd class="text-sm text-stone-200">{{ os.customer_contact_name ?? '—' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-stone-500">Telefone</dt>
              <dd class="text-sm text-stone-200">{{ os.customer_contact_phone ?? '—' }}</dd>
            </div>
            <div class="border-t border-white/5 pt-2.5">
              <div class="flex justify-between">
                <dt class="text-sm text-stone-500">Equipamento</dt>
                <dd>
                  <RouterLink
                    v-if="os.equipment_id"
                    :to="{ name: 'equipments.detail', params: { id: os.equipment_id } }"
                    class="text-sm text-amber-400 hover:text-amber-300"
                  >
                    {{ [os.equipment_brand, os.equipment_model].filter(Boolean).join(' ') }}
                  </RouterLink>
                  <span v-else class="text-sm text-stone-500">—</span>
                </dd>
              </div>
              <div class="mt-1.5 flex justify-between">
                <dt class="text-sm text-stone-500">Nº de Série</dt>
                <dd class="font-mono text-xs text-stone-400">{{ os.equipment_serial_number ?? '—' }}</dd>
              </div>
            </div>
          </dl>
        </div>

        <!-- Diagnóstico -->
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Diagnóstico</h2>
          <div v-if="os.diagnosis_completed_at" class="space-y-3">
            <div>
              <p class="mb-1 text-xs text-stone-500">Notas Internas</p>
              <p class="text-sm text-stone-300">{{ os.diagnosis_internal_notes }}</p>
            </div>
            <div>
              <p class="mb-1 text-xs text-stone-500">Resumo para o Cliente</p>
              <p class="text-sm text-stone-300">{{ os.diagnosis_customer_summary }}</p>
            </div>
            <p class="text-xs text-stone-600">Concluído em {{ fmt(os.diagnosis_completed_at) }}</p>
          </div>
          <p v-else class="text-sm text-stone-500">Diagnóstico ainda não registrado.</p>
          <div class="mt-3 border-t border-white/5 pt-3">
            <p class="text-xs text-stone-500">Problema relatado</p>
            <p class="mt-1 text-sm text-stone-300">{{ os.reported_problem ?? '—' }}</p>
          </div>
        </div>

        <!-- Garantia -->
        <div v-if="isWarrantyReturn" class="rounded-2xl border border-violet-500/20 bg-violet-500/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-violet-400">Garantia</h2>
          <dl class="space-y-2.5">
            <div class="flex justify-between">
              <dt class="text-sm text-stone-500">OS de Origem</dt>
              <dd>
                <RouterLink
                  :to="{ name: 'service-orders.detail', params: { id: os.warranty_origin_service_order_id } }"
                  class="text-sm text-amber-400 hover:text-amber-300"
                >
                  Ver OS original
                </RouterLink>
              </dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-stone-500">Sinal de Elegibilidade</dt>
              <dd>
                <span
                  class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="{
                    'bg-emerald-500/20 text-emerald-300': os.warranty_signal_status === 'eligible',
                    'bg-red-500/20 text-red-400': os.warranty_signal_status === 'ineligible',
                    'bg-stone-500/20 text-stone-400': os.warranty_signal_status === 'unknown' || !os.warranty_signal_status,
                  }"
                >
                  {{ os.warranty_signal_status === 'eligible' ? 'Elegível' : os.warranty_signal_status === 'ineligible' ? 'Inelegível' : 'Desconhecido' }}
                </span>
              </dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-stone-500">Decisão de Cobertura</dt>
              <dd>
                <span
                  class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="{
                    'bg-amber-500/20 text-amber-300': os.warranty_decision_status === 'pending',
                    'bg-emerald-500/20 text-emerald-300': os.warranty_decision_status === 'covered',
                    'bg-red-500/20 text-red-400': os.warranty_decision_status === 'not_covered',
                  }"
                >
                  {{ os.warranty_decision_status === 'pending' ? 'Pendente' : os.warranty_decision_status === 'covered' ? 'Coberto' : os.warranty_decision_status === 'not_covered' ? 'Não Coberto' : '—' }}
                </span>
              </dd>
            </div>
          </dl>
        </div>

        <!-- Atribuição de técnico -->
        <div v-if="canAssign || os.assigned_membership_id" class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Técnico Responsável</h2>
          <div v-if="canAssign" class="flex items-center gap-3">
            <select v-model="selectedMembershipId" class="input flex-1">
              <option :value="null">— Sem atribuição —</option>
              <option v-for="m in memberships" :key="m.id" :value="m.id">
                {{ m.user_name ?? m.user_email }} ({{ m.role }})
              </option>
            </select>
            <button
              :disabled="assignLoading"
              class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
              @click="doAssign"
            >
              {{ assignLoading ? 'Salvando…' : 'Salvar' }}
            </button>
          </div>
          <div v-else-if="os.assigned_membership_name" class="text-sm text-stone-300">
            {{ os.assigned_membership_name }}
          </div>
          <div v-else class="text-sm text-stone-500">Nenhum técnico atribuído.</div>
        </div>

        <!-- Orçamentos -->
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6 lg:col-span-2">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Orçamentos</h2>
          <div v-if="quotes.length === 0" class="text-sm text-stone-500">Nenhum orçamento cadastrado.</div>
          <div v-else class="space-y-4">
            <div
              v-for="q in quotes"
              :key="q.id"
              class="rounded-xl border border-white/5 bg-black/20 p-4"
            >
              <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm font-semibold text-stone-200">Revisão {{ q.revision_number }}</span>
                <div class="flex items-center gap-2">
                  <span :class="quoteStatusLabels[q.status]?.cls" class="rounded-full px-2.5 py-0.5 text-xs font-semibold">
                    {{ quoteStatusLabels[q.status]?.label ?? q.status }}
                  </span>
                  <button
                    v-if="q.status === 'pending'"
                    class="rounded-lg border border-amber-500/30 px-2.5 py-1 text-xs text-amber-400 hover:bg-amber-500/10"
                    @click="doGeneratePortalToken(q.id)"
                  >
                    Gerar Link do Portal
                  </button>
                </div>
              </div>
              <!-- Link do portal gerado -->
              <div v-if="portalLinks[q.id]" class="mb-3 flex items-center gap-2 rounded-lg border border-amber-500/20 bg-amber-500/5 px-3 py-2">
                <span class="flex-1 truncate font-mono text-xs text-amber-300">{{ portalLinks[q.id] }}</span>
                <button class="text-xs text-stone-400 hover:text-stone-200" @click="() => copyLink(portalLinks[q.id])">Copiar</button>
              </div>
              <div class="space-y-1">
                <div
                  v-for="(item, i) in q.line_items"
                  :key="i"
                  class="flex items-center justify-between text-sm"
                >
                  <span class="text-stone-400">{{ item.quantity }}x {{ item.description }}</span>
                  <span class="text-stone-300">{{ fmtMoney(item.unit_price_cents * item.quantity) }}</span>
                </div>
              </div>
              <div class="mt-3 flex justify-between border-t border-white/5 pt-2">
                <span class="text-sm font-semibold text-stone-300">Total</span>
                <span class="font-semibold text-amber-300">{{ fmtMoney(q.total_cents) }}</span>
              </div>
              <div class="mt-2 flex flex-wrap gap-3 text-xs text-stone-500">
                <span>Válido até {{ fmt(q.valid_until) }}</span>
                <span v-if="q.decisioned_at">Decisão: {{ fmt(q.decisioned_at) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Financeiro -->
        <div v-if="financials" class="rounded-2xl border border-white/10 bg-white/5 p-6 lg:col-span-2">
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
              <p class="text-lg font-semibold" :class="financials.summary.outstanding_balance_cents > 0 ? 'text-red-400' : 'text-stone-400'">
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
              <div v-for="p in financials.payments" :key="p.id" class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span class="text-stone-400">{{ paymentMethodLabels[p.payment_method] ?? p.payment_method }} — {{ fmt(p.received_at) }}</span>
                <div class="flex items-center gap-3">
                  <a
                    :href="router.resolve({ name: 'service-orders.documents.receipt', params: { id: route.params.id, paymentId: p.id } }).href"
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

        <!-- Checklist de Recebimento -->
        <div v-if="os.intake_checklist && os.intake_checklist.length > 0" class="rounded-2xl border border-white/10 bg-white/5 p-6 lg:col-span-2">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Checklist de Recebimento</h2>
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

        <!-- Entradas de Execução -->
        <div v-if="os.execution_entries && os.execution_entries.length > 0" class="rounded-2xl border border-white/10 bg-white/5 p-6 lg:col-span-2">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Serviços e Peças Registrados</h2>
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

        <!-- Resultados dos Testes Finais -->
        <div v-if="os.final_test_results && os.final_test_results.length > 0" class="rounded-2xl border border-white/10 bg-white/5 p-6 lg:col-span-2">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Testes Finais</h2>
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

        <!-- Anexos -->
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6 lg:col-span-2">
          <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-500">Anexos</h2>
            <button
              v-if="!isTerminal"
              class="rounded-lg border border-white/10 px-3 py-1 text-xs text-stone-400 transition hover:border-amber-500/30 hover:text-amber-300"
              :disabled="uploadLoading"
              @click="showUploadForm = !showUploadForm"
            >
              {{ uploadLoading ? 'Enviando…' : (showUploadForm ? 'Cancelar' : '+ Adicionar arquivo') }}
            </button>
          </div>
          <!-- Formulário de upload -->
          <div v-if="showUploadForm" class="mb-4 rounded-xl border border-white/10 bg-black/20 p-4 space-y-3">
            <p class="text-xs font-semibold text-stone-400">Tipo de Anexo</p>
            <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
              <select v-model="uploadType" class="input">
                <option value="intake_photo">Foto de Entrada</option>
                <option value="diagnostic_image">Imagem Diagnóstico</option>
                <option value="receipt">Recibo</option>
                <option value="term">Termo</option>
                <option :value="signedServiceOrderCopyType">Via Assinada da O.S.</option>
                <option value="other">Outro</option>
              </select>
              <label class="cursor-pointer whitespace-nowrap rounded-xl border border-amber-500/30 px-4 py-2.5 text-sm text-amber-400 transition hover:bg-amber-500/10">
                Escolher arquivo…
                <input type="file" class="sr-only" :disabled="uploadLoading" @change="uploadAttachment" />
              </label>
            </div>
            <p v-if="uploadError" class="text-xs text-red-400">{{ uploadError }}</p>
          </div>
          <div
            v-if="hasSignedServiceOrderCopy"
            class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300"
          >
            Via assinada da O.S. já vinculada a este atendimento.
          </div>
          <div v-if="attachments.length === 0" class="text-sm text-stone-500">Nenhum anexo.</div>
          <div v-else class="space-y-2">
            <div v-for="a in attachments" :key="a.id" class="flex items-center justify-between rounded-lg border border-white/5 bg-black/20 px-4 py-2.5">
              <div>
                <p class="text-sm text-stone-200">{{ a.file_name }}</p>
                <p class="text-xs text-stone-500">{{ attachmentTypeLabel(a.attachment_type) }} · {{ fmtBytes(a.file_size_bytes) }} · {{ fmt(a.created_at) }}</p>
              </div>
              <button class="text-xs text-amber-400 hover:text-amber-300" @click="downloadAttachment(a.id)">
                Baixar
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Timeline -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Linha do Tempo</h2>
        <div v-if="timeline.length === 0" class="text-sm text-stone-500">Nenhum evento registrado.</div>
        <div v-else class="relative space-y-4 pl-4">
          <div class="absolute left-0 top-0 bottom-0 w-px bg-white/10" />
          <div
            v-for="event in timeline"
            :key="event.id"
            class="relative"
          >
            <div class="absolute -left-4 top-1.5 size-2 rounded-full bg-amber-500/60" />
            <div class="ml-2">
              <p class="text-sm font-medium text-stone-200">{{ event.title }}</p>
              <p class="text-xs text-stone-500">{{ fmt(event.occurred_at) }}</p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.input {
  @apply w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-stone-500 outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20;
}
</style>
