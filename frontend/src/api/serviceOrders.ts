import { apiGet, apiPost, apiPut, apiPatch } from './client'

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
}

export interface ServiceOrderDraftPayload {
  customer_id?: number | null
  customer_contact_id?: number | null
  equipment_id?: number | null
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
  totals: { service_orders: number }
  by_status: Record<string, number>
  technician_workload: Array<{ assigned_membership_id: number; assigned_membership_name: string | null; service_order_count: number }>
  quote_outcomes: Record<string, number>
  received_amounts: { total_received_cents: number; by_payment_method: Record<string, number> }
}

export function listServiceOrders(params?: { q?: string; queue?: ServiceOrderQueue; status?: string[] }) {
  const qs = new URLSearchParams()
  if (params?.q) qs.set('q', params.q)
  if (params?.queue) qs.set('queue', params.queue)
  params?.status?.forEach((s) => qs.append('status[]', s))
  const query = qs.toString() ? `?${qs.toString()}` : ''
  return apiGet<{ data: ServiceOrder[]; meta: { total: number } }>(`/service-orders${query}`)
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

export function deliver(id: number, received_by_name: string, delivery_notes?: string) {
  return apiPost<{ data: ServiceOrder }>(`/service-orders/${id}/delivery`, { received_by_name, delivery_notes })
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
