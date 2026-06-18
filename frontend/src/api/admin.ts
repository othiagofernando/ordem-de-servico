import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from './client'

export interface AdminCompanySummary {
  active_companies: number
  service_orders_this_month: number
  new_companies_last_7_days: number
  subscriptions_expiring_next_15_days: number
}

export interface AdminOverviewSummary extends AdminCompanySummary {
  total_plans: number
  active_subscriptions: number
  suspended_subscriptions: number
}

export interface AdminPlan {
  id: number
  slug: string
  name: string
  limits: {
    max_users: number | null
    max_service_orders_per_month: number | null
    max_attachments_storage_mb: number | null
  }
  reference_price_cents: number | null
  subscribed_companies_count: number
  active_companies_count: number
  expiring_next_15_days_count: number
}

export interface AdminPlanUpsertPayload {
  slug: string
  name: string
  max_users?: number | null
  max_service_orders_per_month?: number | null
  max_attachments_storage_mb?: number | null
  reference_price_cents?: number | null
}

export interface AdminSubscription {
  id: number
  status: string
  started_at: string | null
  expires_at: string | null
  days_until_expiration: number
  is_in_grace_period: boolean
  plan: {
    id: number | null
    slug: string | null
    name: string | null
    limits: {
      max_users: number | null
      max_service_orders_per_month: number | null
      max_attachments_storage_mb: number | null
    }
    reference_price_cents: number | null
  }
  usage: {
    active_users: number
    service_orders_this_month: number
    attachments_storage_bytes: number
    attachments_storage_mb: number
    attachments_storage_limit_mb: number | null
    attachments_storage_remaining_mb: number | null
  }
}

export interface AdminBillingInvoice {
  id: number
  gateway_invoice_id?: string | null
  status: string
  amount_cents: number
  currency: string
  due_at: string | null
  paid_at: string | null
  invoice_url: string | null
  pdf_url: string | null
  created_at?: string | null
  updated_at?: string | null
  payload?: Record<string, unknown> | null
}

export interface AdminBillingInvoiceListEntry extends AdminBillingInvoice {
  company: {
    id: number | null
    name: string | null
    document: string | null
    email: string | null
    status: string | null
  }
  subscription: {
    id: number | null
    status: string | null
    plan: {
      id: number | null
      name: string | null
      slug: string | null
    }
  }
}

export interface AdminBillingInvoiceSummary {
  total: number
  pending: number
  overdue: number
  paid: number
}

export interface AdminAlertSummary {
  overdue_invoices: number
  expiring_subscriptions: number
  suspended_companies: number
  recent_admin_events: number
}

export interface AdminAlertInvoice {
  id: number
  status: string
  amount_cents: number
  currency: string
  due_at: string | null
  invoice_url: string | null
  company: {
    id: number | null
    name: string | null
    document: string | null
  }
  plan: {
    id: number | null
    name: string | null
  }
}

export interface AdminAlertSubscription {
  company: {
    id: number | null
    name: string | null
    document: string | null
    status: string | null
  }
  subscription: {
    id: number | null
    status: string | null
    expires_at: string | null
    days_until_expiration: number | null
    plan: {
      id: number | null
      name: string | null
    }
  }
}

export interface AdminAlertSuspendedCompany {
  id: number
  name: string
  document: string | null
  status: string
  subscription: {
    id: number | null
    status: string | null
    plan: {
      id: number | null
      name: string | null
    }
  }
}

export interface AdminAlertEvent {
  id: number
  company_id: number
  company_name: string | null
  actor_name: string | null
  actor_type: string
  event_type: string
  occurred_at: string | null
}

export interface AdminAlertsPayload {
  summary: AdminAlertSummary
  overdue_invoices: AdminAlertInvoice[]
  expiring_subscriptions: AdminAlertSubscription[]
  suspended_companies: AdminAlertSuspendedCompany[]
  recent_admin_events: AdminAlertEvent[]
}

export interface AdminSubscriptionListEntry {
  company: {
    id: number | null
    name: string | null
    document: string | null
    email: string | null
    status: string | null
  }
  subscription: AdminSubscription | null
  latest_invoice: AdminBillingInvoice | null
}

export interface AdminSubscriptionSummary {
  total: number
  active: number
  suspended: number
  cancelled: number
  expiring_next_15_days: number
}

export interface AdminAuditEntry {
  id: number
  company_id: number
  company_name?: string | null
  actor_membership_id: number | null
  actor_name: string | null
  actor_type: 'super_admin' | 'tenant_membership' | string
  category: string
  event_type: string
  auditable_type: string | null
  auditable_id: number | null
  context: Record<string, unknown> | null
  before_values: Record<string, unknown> | null
  after_values: Record<string, unknown> | null
  occurred_at: string | null
}

export interface AdminCompany {
  id: number
  uuid: string
  name: string
  slug: string | null
  document: string | null
  email: string | null
  created_at: string | null
  status: 'active' | 'suspended' | 'cancelled' | string
  is_active: boolean
  memberships_count: number
  service_orders_count: number
  subscription: AdminSubscription | null
  trade_name?: string | null
  phone?: string | null
  website?: string | null
  address_line_1?: string | null
  address_line_2?: string | null
  district?: string | null
  city?: string | null
  state?: string | null
  postal_code?: string | null
  passwordless_code_enabled?: boolean
  magic_link_enabled?: boolean
  tenant_url?: string | null
  deleted_at?: string | null
  recent_invoices?: AdminBillingInvoice[]
  recent_audits?: AdminAuditEntry[]
}

export interface AdminCompanyUpsertPayload {
  name: string
  trade_name?: string | null
  document?: string | null
  email?: string | null
  phone?: string | null
  website?: string | null
  address_line_1?: string | null
  address_line_2?: string | null
  district?: string | null
  city?: string | null
  state?: string | null
  postal_code?: string | null
  status: string
  is_active: boolean
  passwordless_code_enabled: boolean
  magic_link_enabled: boolean
}

export interface AdminOverviewPayload {
  summary: AdminOverviewSummary
  expiring_companies: AdminCompany[]
  recent_companies: AdminCompany[]
  suspended_companies: AdminCompany[]
}

export function getAdminOverview() {
  return apiGet<AdminOverviewPayload>('/admin/overview')
}

export function getAdminAlerts() {
  return apiGet<AdminAlertsPayload>('/admin/alerts')
}

export function listAdminCompanies(filters: { query?: string; plan_id?: number } = {}) {
  const qs = new URLSearchParams()
  if (filters.query?.trim()) qs.set('q', filters.query.trim())
  if (filters.plan_id) qs.set('plan_id', String(filters.plan_id))
  return apiGet<{ data: AdminCompany[]; summary: AdminCompanySummary }>(`/admin/companies${qs.toString() ? `?${qs.toString()}` : ''}`)
}

export function listAdminPlans() {
  return apiGet<{ data: AdminPlan[] }>('/admin/plans')
}

export function getAdminPlan(planId: number) {
  return apiGet<{ data: AdminPlan }>(`/admin/plans/${planId}`)
}

export function createAdminPlan(payload: AdminPlanUpsertPayload) {
  return apiPost<{ data: AdminPlan }>('/admin/plans', payload)
}

export function updateAdminPlan(planId: number, payload: AdminPlanUpsertPayload) {
  return apiPut<{ data: AdminPlan }>(`/admin/plans/${planId}`, payload)
}

export function getAdminCompany(companyId: number) {
  return apiGet<{ data: AdminCompany }>(`/admin/companies/${companyId}`)
}

export function createAdminCompany(payload: AdminCompanyUpsertPayload) {
  return apiPost<{ data: AdminCompany }>('/admin/companies', payload)
}

export function updateAdminCompany(companyId: number, payload: AdminCompanyUpsertPayload) {
  return apiPut<{ data: AdminCompany }>(`/admin/companies/${companyId}`, payload)
}

export function suspendAdminCompany(companyId: number) {
  return apiPatch<{ data: AdminCompany }>(`/admin/companies/${companyId}/suspend`)
}

export function activateAdminCompany(companyId: number) {
  return apiPatch<{ data: AdminCompany }>(`/admin/companies/${companyId}/activate`)
}

export function deleteAdminCompany(companyId: number, confirmation: string) {
  return apiDelete<void>(`/admin/companies/${companyId}`, { confirmation })
}

export function updateAdminCompanySubscription(
  companyId: number,
  payload: { plan_id: number; expires_at: string; started_at?: string | null; status?: string },
) {
  return apiPut<{ data: AdminSubscription }>(`/admin/companies/${companyId}/subscription`, payload)
}

export function extendAdminCompanySubscription(
  companyId: number,
  payload: { days?: number; months?: number },
) {
  return apiPost<{ data: AdminSubscription }>(`/admin/companies/${companyId}/subscription/extend`, payload)
}

export function listAdminCompanyAudits(
  companyId: number,
  filters: { category?: string; event_type?: string; limit?: number } = {},
) {
  const qs = new URLSearchParams()
  if (filters.category?.trim()) qs.set('category', filters.category.trim())
  if (filters.event_type?.trim()) qs.set('event_type', filters.event_type.trim())
  if (filters.limit) qs.set('limit', String(filters.limit))
  return apiGet<{ data: AdminAuditEntry[] }>(`/admin/companies/${companyId}/audits${qs.toString() ? `?${qs.toString()}` : ''}`)
}

export function listAdminAudits(
  filters: { company_id?: number; category?: string; event_type?: string; limit?: number } = {},
) {
  const qs = new URLSearchParams()
  if (filters.company_id) qs.set('company_id', String(filters.company_id))
  if (filters.category?.trim()) qs.set('category', filters.category.trim())
  if (filters.event_type?.trim()) qs.set('event_type', filters.event_type.trim())
  if (filters.limit) qs.set('limit', String(filters.limit))
  return apiGet<{ data: AdminAuditEntry[] }>(`/admin/audits${qs.toString() ? `?${qs.toString()}` : ''}`)
}

export function getAdminAudit(auditId: number) {
  return apiGet<{ data: AdminAuditEntry }>(`/admin/audits/${auditId}`)
}

export function listAdminSubscriptions(
  filters: { query?: string; status?: string; plan_id?: number; expiring?: boolean } = {},
) {
  const qs = new URLSearchParams()
  if (filters.query?.trim()) qs.set('q', filters.query.trim())
  if (filters.status?.trim()) qs.set('status', filters.status.trim())
  if (filters.plan_id) qs.set('plan_id', String(filters.plan_id))
  if (filters.expiring) qs.set('expiring', '1')

  return apiGet<{ data: AdminSubscriptionListEntry[]; summary: AdminSubscriptionSummary }>(
    `/admin/subscriptions${qs.toString() ? `?${qs.toString()}` : ''}`,
  )
}

export function listAdminBillingInvoices(
  filters: { query?: string; status?: string; plan_id?: number; overdue?: boolean } = {},
) {
  const qs = new URLSearchParams()
  if (filters.query?.trim()) qs.set('q', filters.query.trim())
  if (filters.status?.trim()) qs.set('status', filters.status.trim())
  if (filters.plan_id) qs.set('plan_id', String(filters.plan_id))
  if (filters.overdue) qs.set('overdue', '1')

  return apiGet<{ data: AdminBillingInvoiceListEntry[]; summary: AdminBillingInvoiceSummary }>(
    `/admin/billing/invoices${qs.toString() ? `?${qs.toString()}` : ''}`,
  )
}

export function getAdminBillingInvoice(invoiceId: number) {
  return apiGet<{ data: AdminBillingInvoiceListEntry }>(`/admin/billing/invoices/${invoiceId}`)
}
