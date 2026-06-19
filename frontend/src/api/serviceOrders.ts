import { apiDownload, apiGet, apiPost, apiPut, apiPatch, apiDelete } from './client'

export type ServiceOrderStatus =
  | 'draft'
  | 'open'
  | 'in_progress'
  | 'completed'
  | 'ready_for_pickup'
  | 'delivered'
  | 'cancelled'
  | 'no_repair'

export interface TimelineEvent {
  id: number
  type: string
  title: string
  visibility: string
  payload: Record<string, unknown> | null
  occurred_at: string | null
  actor_membership_id: number | null
}

export interface ServiceOrder {
  id: number
  reference: string
  draft_reference: string | null
  order_number: string | null
  title: string
  status: ServiceOrderStatus
  customer_id: number | null
  customer_name: string | null
  customer_document: string | null
  customer_contact_id: number | null
  customer_contact_name: string | null
  customer_contact_phone: string | null
  equipment_id: number | null
  equipment_description: string | null
  equipment_serial_number: string | null
  equipment_brand: string | null
  equipment_model: string | null
  reported_problem: string | null
  intake_checklist: Array<{ label: string; checked?: boolean | null; notes?: string | null }> | null
  diagnosis_internal_notes: string | null
  diagnosis_customer_summary: string | null
  diagnosis_completed_at: string | null
  execution_started_at: string | null
  technical_completed_at: string | null
  delivered_at: string | null
  opened_at: string | null
  assigned_membership_id: number | null
  assigned_membership_name: string | null
  latest_quote_status: string | null
  warranty_signal_status: string | null
  warranty_signal_reason: string | null
  warranty_signal_expires_at: string | null
  warranty_decision_status: string | null
  warranty_decision_override: boolean
  warranty_decision_notes: string | null
  warranty_decided_at: string | null
  warranty_origin_service_order_id: number | null
  final_test_results: Array<{ label: string; passed: boolean; notes?: string | null }> | null
  execution_entries: Array<{ id: number; entry_type: string; description: string; quantity: number; notes: string | null }> | null
  deleted_at: string | null
}

export interface TrashedServiceOrder {
  id: number
  reference: string
  title: string
  status: ServiceOrderStatus
  customer_name: string | null
  equipment_brand: string | null
  equipment_model: string | null
  equipment_serial_number: string | null
  deleted_at: string
}

export interface ServiceOrderDraftPayload {
  customer_id?: number | null
  customer_contact_id?: number | null
  equipment_id?: number | null
  intake_checklist_template_id?: number | null
  equipment_description?: string | null
  reported_problem?: string | null
  intake_checklist?: Array<{ label: string; checked?: boolean | null; notes?: string | null }>
}

export type ServiceOrderQueue =
  | 'drafts'
  | 'open'
  | 'pending_quote'
  | 'in_progress'
  | 'ready_for_pickup'
  | 'terminal'
  | 'warranty_returns'

export interface OverviewData {
  scope: {
    mode: 'tenant' | 'assigned'
    membership_id: number
    membership_name: string | null
  }
  filters: Record<string, unknown>
  totals: { service_orders: number }
  queues: Record<string, number>
  attention: {
    unassigned_open: number
    pending_quote: number
    ready_for_pickup: number
    warranty_pending_review: number
  }
  by_status: Record<string, number>
  technician_workload: Array<{ assigned_membership_id: number; assigned_membership_name: string | null; service_order_count: number }>
  quote_outcomes: Record<string, number>
  received_amounts: { total_received_cents: number; by_payment_method: Record<string, number> }
  warranty_returns: {
    total: number
    by_decision_status: Record<string, number>
  }
}

export interface ServiceOrderReportRow {
  id: number
  reference: string
  order_number: number | null
  status: string
  opened_at: string | null
  customer_name: string | null
  customer_document: string | null
  equipment_summary: string
  assigned_membership_name: string | null
  latest_quote_status: string | null
  warranty_decision_status: string | null
}

export interface PaginationMeta {
  total: number
  per_page: number
  current_page: number
  last_page: number
  from: number
  to: number
}

export interface ServiceOrderReportResponse {
  data: ServiceOrderReportRow[]
  meta: PaginationMeta & { by_status?: Record<string, number> }
  filters: Record<string, unknown>
}

export interface FinancialReportRow {
  service_order_id: number
  reference: string
  order_number: number | null
  status: string
  customer_name: string | null
  assigned_membership_name: string | null
  total_charged_cents: number
  total_paid_cents: number
  outstanding_balance_cents: number
  payment_methods: string[]
  last_payment_at: string | null
}

export interface FinancialReportResponse {
  data: FinancialReportRow[]
  meta: PaginationMeta & {
    totals: {
      total_charged_cents: number
      total_paid_cents: number
      outstanding_balance_cents: number
    }
  }
  filters: Record<string, unknown>
}

export interface ServiceOrderReportFilters {
  q?: string
  queue?: ServiceOrderQueue
  opened_from?: string
  opened_to?: string
  page?: number
  per_page?: number
}

export interface FinancialReportFilters {
  q?: string
  payment_method?: string
  balance_state?: 'settled' | 'outstanding'
  received_from?: string
  received_to?: string
  page?: number
  per_page?: number
}

function buildReportQuery(filters: Record<string, string | undefined>) {
  const qs = new URLSearchParams()

  Object.entries(filters).forEach(([key, value]) => {
    if (value) qs.set(key, value)
  })

  const query = qs.toString()
  return query ? `?${query}` : ''
}

export function listServiceOrders(params?: {
  q?: string
  queue?: ServiceOrderQueue
  status?: string[]
  page?: number
  per_page?: number
}) {
  const qs = new URLSearchParams()
  if (params?.q) qs.set('q', params.q)
  if (params?.queue) qs.set('queue', params.queue)
  params?.status?.forEach((s) => qs.append('status[]', s))
  if (params?.page) qs.set('page', String(params.page))
  if (params?.per_page) qs.set('per_page', String(params.per_page))
  const query = qs.toString() ? `?${qs.toString()}` : ''
  return apiGet<{ data: ServiceOrder[]; meta: PaginationMeta }>(`/service-orders${query}`)
}

export function getServiceOrder(id: number) {
  return apiGet<{ data: ServiceOrder }>(`/service-orders/${id}`)
}

export function getTimeline(id: number) {
  return apiGet<{ data: TimelineEvent[] }>(`/service-orders/${id}/timeline`)
}

export function getOverview() {
  return apiGet<{ data: OverviewData }>('/service-orders/reports/overview')
}

export function getServiceOrderReport(filters?: ServiceOrderReportFilters) {
  const query = buildReportQuery({
    q: filters?.q,
    queue: filters?.queue,
    opened_from: filters?.opened_from,
    opened_to: filters?.opened_to,
    page: filters?.page !== undefined ? String(filters.page) : undefined,
    per_page: filters?.per_page !== undefined ? String(filters.per_page) : undefined,
  })

  return apiGet<ServiceOrderReportResponse>(`/service-orders/reports/service-orders${query}`)
}

export function downloadServiceOrderReportCsv(filters?: ServiceOrderReportFilters) {
  const query = buildReportQuery({
    q: filters?.q,
    queue: filters?.queue,
    opened_from: filters?.opened_from,
    opened_to: filters?.opened_to,
    format: 'csv',
  })

  return apiDownload(`/service-orders/reports/service-orders${query}`, 'relatorio-ordens-servico.csv')
}

export function getServiceOrderReportForPrint(filters?: ServiceOrderReportFilters) {
  const query = buildReportQuery({
    q: filters?.q,
    queue: filters?.queue,
    opened_from: filters?.opened_from,
    opened_to: filters?.opened_to,
    format: 'print',
  })
  return apiGet<{ data: ServiceOrderReportRow[] }>(`/service-orders/reports/service-orders${query}`)
}

export function getFinancialReport(filters?: FinancialReportFilters) {
  const query = buildReportQuery({
    q: filters?.q,
    payment_method: filters?.payment_method,
    balance_state: filters?.balance_state,
    received_from: filters?.received_from,
    received_to: filters?.received_to,
    page: filters?.page !== undefined ? String(filters.page) : undefined,
    per_page: filters?.per_page !== undefined ? String(filters.per_page) : undefined,
  })

  return apiGet<FinancialReportResponse>(`/service-orders/reports/financial${query}`)
}

export function downloadFinancialReportCsv(filters?: FinancialReportFilters) {
  const query = buildReportQuery({
    q: filters?.q,
    payment_method: filters?.payment_method,
    balance_state: filters?.balance_state,
    received_from: filters?.received_from,
    received_to: filters?.received_to,
    format: 'csv',
  })

  return apiDownload(`/service-orders/reports/financial${query}`, 'relatorio-financeiro.csv')
}

export function getFinancialReportForPrint(filters?: FinancialReportFilters) {
  const query = buildReportQuery({
    q: filters?.q,
    payment_method: filters?.payment_method,
    balance_state: filters?.balance_state,
    received_from: filters?.received_from,
    received_to: filters?.received_to,
    format: 'print',
  })
  return apiGet<{
    data: FinancialReportRow[]
    totals: { total_charged_cents: number; total_paid_cents: number; outstanding_balance_cents: number }
  }>(`/service-orders/reports/financial${query}`)
}

export function createDraft(payload: ServiceOrderDraftPayload) {
  return apiPost<{ data: ServiceOrder }>('/service-orders/drafts', payload)
}

export function updateDraft(id: number, payload: ServiceOrderDraftPayload) {
  return apiPut<{ data: ServiceOrder }>(`/service-orders/drafts/${id}`, payload)
}

export function openDraft(id: number) {
  return apiPost<{ data: ServiceOrder }>(`/service-orders/drafts/${id}/open`)
}

export function updateDiagnosis(id: number, internal_notes: string, customer_summary: string) {
  return apiPut<{ data: ServiceOrder }>(`/service-orders/${id}/diagnosis`, { internal_notes, customer_summary })
}

export function getQuotes(serviceOrderId: number) {
  return apiGet<{ data: QuoteRevision[] }>(`/service-orders/${serviceOrderId}/quotes`)
}

export function createQuote(serviceOrderId: number, payload: QuotePayload) {
  return apiPost<{ data: QuoteRevision }>(`/service-orders/${serviceOrderId}/quotes`, payload)
}

export interface QuoteLineItem {
  description: string
  quantity: number
  unit_price_cents: number
  note?: string | null
}

export interface QuoteRevision {
  id: number
  revision_number: number
  status: 'pending' | 'approved' | 'rejected' | 'expired'
  line_items: QuoteLineItem[]
  total_cents: number
  currency: string
  notes: string | null
  valid_until: string
  submitted_at: string
  decisioned_at: string | null
}

export interface QuotePayload {
  line_items: QuoteLineItem[]
  notes?: string | null
  valid_until: string
}

export function startExecution(id: number) {
  return apiPost<{ data: ServiceOrder }>(`/service-orders/${id}/execution/start`)
}

export function completeExecution(id: number) {
  return apiPost<{ data: ServiceOrder }>(`/service-orders/${id}/execution/complete`)
}

export function markReadyForPickup(id: number) {
  return apiPost<{ data: ServiceOrder }>(`/service-orders/${id}/ready-for-pickup`)
}

export function deliver(
  id: number,
  payload: {
    received_by_name: string
    notes?: string
    allow_balance_override?: boolean
    balance_override_reason?: string
  },
) {
  return apiPost<{ data: ServiceOrder }>(`/service-orders/${id}/delivery`, payload)
}

export function recordPayment(
  serviceOrderId: number,
  payload: { amount_cents: number; payment_method: string; notes?: string },
) {
  return apiPost(`/service-orders/${serviceOrderId}/financials/payments`, payload)
}

export function cancelServiceOrder(id: number, notes: string) {
  return apiPost(`/service-orders/${id}/close/cancel`, { notes })
}

export function closeNoRepair(id: number, notes: string) {
  return apiPost(`/service-orders/${id}/close/no-repair`, { notes })
}

export function generatePortalToken(serviceOrderId: number, quoteRevisionId: number) {
  return apiPost<{ data: { portal_token: string; portal_token_expires_at: string; revision_number: number } }>(
    `/service-orders/${serviceOrderId}/quotes/${quoteRevisionId}/portal-token`,
  )
}

export interface WorkEntry {
  description: string
  quantity?: number
  notes?: string | null
}

export function recordWorkEntries(
  id: number,
  payload: { services?: WorkEntry[]; parts?: WorkEntry[] },
) {
  return apiPost(`/service-orders/${id}/execution/entries`, payload)
}

export interface FinalTestResult {
  label: string
  passed: boolean
  notes?: string | null
}

export function completeExecutionWithTests(id: number, final_test_results: FinalTestResult[]) {
  return apiPost(`/service-orders/${id}/execution/complete`, { final_test_results })
}

export interface FinancialSummary {
  currency: string
  total_charged_cents: number
  total_paid_cents: number
  outstanding_balance_cents: number
}

export interface Charge {
  id: number
  charge_type: string
  description: string
  amount_cents: number
  currency: string
  created_at: string | null
}

export interface Payment {
  id: number
  amount_cents: number
  currency: string
  payment_method: string
  notes: string | null
  received_at: string | null
}

export interface Financials {
  service_order_id: number
  summary: FinancialSummary
  charges: Charge[]
  payments: Payment[]
}

export function getFinancials(id: number) {
  return apiGet<{ data: Financials }>(`/service-orders/${id}/financials`)
}

export function assignTechnician(id: number, assigned_membership_id: number | null) {
  return apiPatch(`/service-orders/${id}/assignment`, { assigned_membership_id })
}

export interface Attachment {
  id: number
  attachment_type: string
  file_name: string
  mime_type: string
  file_size_bytes: number
  notes: string | null
  uploaded_by_membership_id: number | null
  created_at: string | null
}

export function getAttachments(id: number) {
  return apiGet<{ data: Attachment[] }>(`/service-orders/${id}/attachments`)
}

export interface PrintableServiceOrderPayload {
  generated_at: string
  document: {
    version: number
    issue_count: number
    issue_type: 'initial_issue' | 'reprint'
    snapshot_issued_at: string | null
    snapshot_issued_by_membership_id: number | null
    snapshot_issued_by_name: string | null
    issued_at: string | null
    issued_by_membership_id: number | null
    issued_by_name: string | null
  }
  company: {
    name: string | null
    document: string | null
    email: string | null
    phone: string | null
  }
  service_order: {
    id: number
    reference: string
    order_number: number | null
    status: ServiceOrderStatus
    opened_at: string | null
    title: string
    reported_problem: string | null
    equipment_description: string | null
    customer: {
      name: string | null
      document: string | null
      contact_name: string | null
      contact_phone: string | null
    }
    equipment: {
      category: string | null
      brand: string | null
      model: string | null
      serial_number: string | null
      accessories: string | null
    }
    intake_checklist: Array<{ label: string; checked: boolean; notes: string | null }>
    observations: string[]
    signature_blocks: Array<{ key: string; label: string }>
  }
  copies: Array<{ key: string; label: string }>
}

export function getPrintableServiceOrder(id: number) {
  return apiGet<{ data: PrintableServiceOrderPayload }>(`/service-orders/${id}/printable`)
}

export interface PrintableQuoteDocumentPayload {
  document_type: 'quote'
  generated_at: string
  company: {
    name: string | null
    document: string | null
    email: string | null
    phone: string | null
  }
  service_order: {
    id: number
    reference: string
    order_number: number | null
    status: ServiceOrderStatus
    opened_at: string | null
    reported_problem: string | null
    equipment_description: string | null
    equipment_brand: string | null
    equipment_model: string | null
    equipment_serial_number: string | null
  }
  customer: {
    name: string | null
    document: string | null
    contact_name: string | null
    contact_phone: string | null
  }
  quote: {
    id: number
    revision_number: number
    status: 'pending' | 'approved' | 'rejected' | 'expired'
    currency: string
    total_cents: number
    notes: string | null
    valid_until: string | null
    submitted_at: string | null
    line_items: Array<{
      description: string
      quantity: number
      unit_price_cents: number
      line_total_cents: number
    }>
  }
}

export interface PrintableReceiptDocumentPayload {
  document_type: 'receipt'
  generated_at: string
  company: PrintableQuoteDocumentPayload['company']
  service_order: PrintableQuoteDocumentPayload['service_order']
  customer: PrintableQuoteDocumentPayload['customer']
  receipt: {
    payment_id: number
    amount_cents: number
    currency: string
    payment_method: string
    received_at: string | null
    received_by_membership_id: number | null
    notes: string | null
    outstanding_balance_before_payment_cents: number
    balance_after_payment_cents: number
  }
}

export interface PrintableDeliveryTermPayload {
  document_type: 'delivery_term'
  generated_at: string
  company: PrintableQuoteDocumentPayload['company']
  service_order: PrintableQuoteDocumentPayload['service_order'] & {
    delivery_notes: string | null
    received_by_name: string | null
    delivered_at: string | null
    outstanding_balance_cents: number
  }
  customer: PrintableQuoteDocumentPayload['customer']
}

export type PrintableOperationalDocumentPayload =
  | PrintableQuoteDocumentPayload
  | PrintableReceiptDocumentPayload
  | PrintableDeliveryTermPayload

export function getPrintableCurrentQuote(id: number) {
  return apiGet<{ data: PrintableQuoteDocumentPayload }>(`/service-orders/${id}/documents/quote`)
}

export function getPrintablePaymentReceipt(serviceOrderId: number, paymentId: number) {
  return apiGet<{ data: PrintableReceiptDocumentPayload }>(
    `/service-orders/${serviceOrderId}/documents/payments/${paymentId}/receipt`,
  )
}

export function getPrintableDeliveryTerm(id: number) {
  return apiGet<{ data: PrintableDeliveryTermPayload }>(`/service-orders/${id}/documents/delivery-term`)
}

export function deriveQuoteCharge(id: number) {
  return apiPost(`/service-orders/${id}/financials/charges/derive-approved-quote`)
}

export function addDiagnosticFee(id: number, description: string, amount_cents: number, notes?: string) {
  return apiPost(`/service-orders/${id}/financials/charges/diagnostic-fee`, { description, amount_cents, notes })
}

export function addAdjustment(id: number, description: string, amount_cents: number, notes?: string) {
  return apiPost(`/service-orders/${id}/financials/charges/adjustments`, { description, amount_cents, notes })
}

export function createWarrantyReturn(id: number, reported_problem: string) {
  return apiPost<{ data: ServiceOrder }>(`/service-orders/${id}/warranty-returns`, { reported_problem })
}

export function decideWarrantyCoverage(id: number, decision: 'covered' | 'not_covered', notes: string) {
  return apiPatch(`/service-orders/${id}/warranty-coverage`, { decision, notes })
}

export function trashServiceOrder(id: number, password: string) {
  return apiDelete(`/service-orders/${id}`, { password })
}

export function listTrashedServiceOrders() {
  return apiGet<{ data: TrashedServiceOrder[]; meta: { total: number; current_page: number; last_page: number } }>('/service-orders/trash')
}

export function restoreServiceOrder(id: number, password: string) {
  return apiPost(`/service-orders/${id}/restore`, { password })
}

export function forceDeleteServiceOrder(id: number, password: string) {
  return apiDelete(`/service-orders/${id}/force`, { password })
}

export interface TrendsMonth {
  month: string
  label: string
  opened: number
  completed: number
  received_cents: number
}

export interface TechnicianReportRow {
  id: number | null
  name: string
  total: number
  in_progress: number
  completed: number
  cancelled: number
  no_repair: number
  pending: number
  total_received_cents: number
}

export interface TechnicianReportFilters {
  opened_from?: string
  opened_to?: string
}

export function getTrends(months?: number): Promise<{ data: { months: TrendsMonth[] } }> {
  const params = months ? `?months=${months}` : ''
  return apiGet<{ data: { months: TrendsMonth[] } }>(`/service-orders/reports/trends${params}`)
}

export function getTechnicianReport(filters?: TechnicianReportFilters): Promise<{ data: TechnicianReportRow[] }> {
  const params = new URLSearchParams()
  if (filters?.opened_from) params.set('opened_from', filters.opened_from)
  if (filters?.opened_to) params.set('opened_to', filters.opened_to)
  const qs = params.toString()
  return apiGet<{ data: TechnicianReportRow[] }>(`/service-orders/reports/by-technician${qs ? '?' + qs : ''}`)
}

export function downloadTechnicianReportCsv(filters?: TechnicianReportFilters) {
  const params = new URLSearchParams()
  if (filters?.opened_from) params.set('opened_from', filters.opened_from)
  if (filters?.opened_to) params.set('opened_to', filters.opened_to)
  params.set('format', 'csv')
  const qs = params.toString()
  return apiDownload(`/service-orders/reports/by-technician?${qs}`, 'relatorio-por-tecnico.csv')
}
