import { computed, type Ref } from 'vue'
import type { Router } from 'vue-router'
import type { Attachment, QuoteRevision, ServiceOrder } from '../../../api/serviceOrders'
import { useTenantRouting } from '../../../composables/useTenantRouting'

export function useServiceOrderDerivedState(
  os: Ref<ServiceOrder | null>,
  quotes: Ref<QuoteRevision[]>,
  attachments: Ref<Attachment[]>,
  router: Router,
) {
  const { tenantRoute } = useTenantRouting()
  const signedServiceOrderCopyType = 'signed_service_order_copy'
  const hasApprovedQuote = computed(() => quotes.value.some((quote) => quote.status === 'approved'))

  const isTerminal = computed(() => ['cancelled', 'no_repair', 'delivered'].includes(os.value?.status ?? ''))
  const canOpenDraft = computed(() => os.value?.status === 'draft')
  const canDiagnose = computed(() => os.value?.status === 'open')
  const canStartExec = computed(() => (
    os.value?.status === 'open'
    && !!os.value?.diagnosis_completed_at
    && os.value.assigned_membership_id !== null
    && hasApprovedQuote.value
  ))
  const canAddQuote = computed(() => ['open', 'in_progress'].includes(os.value?.status ?? ''))
  const canCompleteExec = computed(() => os.value?.status === 'in_progress')
  const canReadyPick = computed(() => os.value?.status === 'completed')
  const canDeliver = computed(() => os.value?.status === 'ready_for_pickup')
  const canPay = computed(() => !['draft', 'cancelled', 'no_repair'].includes(os.value?.status ?? ''))
  const canCancel = computed(() => !isTerminal.value && os.value?.status !== undefined)
  const canAssign = computed(() => ['open', 'in_progress'].includes(os.value?.status ?? ''))
  const canWorkEntries = computed(() => os.value?.status === 'in_progress')
  const canWarrantyReturn = computed(() => os.value?.status === 'delivered' && !os.value?.warranty_origin_service_order_id)
  const isWarrantyReturn = computed(() => !!os.value?.warranty_origin_service_order_id)
  const canDecideWarranty = computed(() => isWarrantyReturn.value && os.value?.warranty_decision_status === 'pending_inspection')

  const printableHref = computed(() => (
    os.value && os.value.status !== 'draft'
      ? router.resolve(tenantRoute('service-orders.print', { params: { id: os.value.id } })).href
      : '#'
  ))

  const quotePrintableHref = computed(() => (
    os.value && quotes.value.length > 0
      ? router.resolve(tenantRoute('service-orders.documents.quote', { params: { id: os.value.id } })).href
      : '#'
  ))

  const deliveryTermPrintableHref = computed(() => (
    os.value?.status === 'delivered'
      ? router.resolve(tenantRoute('service-orders.documents.delivery', { params: { id: os.value.id } })).href
      : '#'
  ))

  const hasSignedServiceOrderCopy = computed(() =>
    attachments.value.some((attachment) => attachment.attachment_type === signedServiceOrderCopyType),
  )

  return {
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
  }
}
