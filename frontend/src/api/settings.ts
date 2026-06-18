import { apiGet, apiPostForm, apiPut } from './client'

export interface CompanySettings {
  company_id: number
  profile: {
    name: string
    slug: string
    trade_name: string | null
    document: string | null
    email: string | null
    phone: string | null
    website: string | null
    tenant_url: string | null
    logo_url: string | null
    address: {
      line_1: string | null
      line_2: string | null
      district: string | null
      city: string | null
      state: string | null
      postal_code: string | null
    }
  }
  branding: { brand_name: string; customer_contact_line: string | null }
  service_orders: { reference_prefix: string }
  portal: {
    intro_text: string | null
    footer_text: string | null
    approved_text: string
    rejected_text: string
  }
  quotes: { default_validity_days: number }
  documents: { footer_text: string | null }
  intake: { checklists_enabled: boolean }
  finance: { block_delivery_with_outstanding_balance: boolean; currency: string }
  communications: { notification_footer_text: string | null }
  notifications: {
    quote_available: boolean
    quote_available_text: string | null
    quote_expiring: boolean
    quote_expiring_text: string | null
    ready_for_pickup: boolean
    ready_for_pickup_text: string | null
    payment_receipt: boolean
    payment_receipt_text: string | null
    service_order_opened: boolean
    service_order_opened_text: string | null
    service_order_in_progress: boolean
    service_order_in_progress_text: string | null
    service_order_completed: boolean
    service_order_completed_text: string | null
    service_order_delivered: boolean
    service_order_delivered_text: string | null
    service_order_cancelled: boolean
    service_order_cancelled_text: string | null
    service_order_no_repair: boolean
    service_order_no_repair_text: string | null
  }
  auth: {
    passwordless_code_enabled: boolean
    magic_link_enabled: boolean
  }
}

export interface SettingsPayload {
  name?: string
  slug?: string
  trade_name?: string | null
  document?: string | null
  email?: string | null
  phone?: string | null
  address_line_1?: string | null
  address_line_2?: string | null
  district?: string | null
  city?: string | null
  state?: string | null
  postal_code?: string | null
  website?: string | null
  brand_name?: string | null
  customer_contact_line?: string | null
  service_order_reference_prefix?: string | null
  portal_intro_text?: string | null
  portal_footer_text?: string | null
  portal_approved_text?: string | null
  portal_rejected_text?: string | null
  document_footer_text?: string | null
  intake_checklists_enabled?: boolean
  currency?: string
  default_quote_validity_days?: number
  block_delivery_with_outstanding_balance?: boolean
  notify_quote_available?: boolean
  notify_quote_available_text?: string | null
  notify_quote_expiring?: boolean
  notify_quote_expiring_text?: string | null
  notify_ready_for_pickup?: boolean
  notify_ready_for_pickup_text?: string | null
  notify_payment_receipt?: boolean
  notify_payment_receipt_text?: string | null
  notify_service_order_opened?: boolean
  notify_service_order_opened_text?: string | null
  notify_service_order_in_progress?: boolean
  notify_service_order_in_progress_text?: string | null
  notify_service_order_completed?: boolean
  notify_service_order_completed_text?: string | null
  notify_service_order_delivered?: boolean
  notify_service_order_delivered_text?: string | null
  notify_service_order_cancelled?: boolean
  notify_service_order_cancelled_text?: string | null
  notify_service_order_no_repair?: boolean
  notify_service_order_no_repair_text?: string | null
  notification_footer_text?: string | null
  passwordless_code_enabled?: boolean
  magic_link_enabled?: boolean
}

export function getSettings() {
  return apiGet<{ data: CompanySettings }>('/settings')
}

export function updateSettings(payload: SettingsPayload) {
  return apiPut<{ data: CompanySettings }>('/settings', payload)
}

export function uploadCompanyLogo(file: File) {
  const formData = new FormData()
  formData.append('logo', file)

  return apiPostForm<{ data: { logo_url: string; logo_path: string } }>('/settings/logo', formData)
}
