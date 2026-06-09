import { apiGet, apiPut } from './client'

export interface CompanySettings {
  company_id: number
  branding: { brand_name: string; customer_contact_line: string | null }
  service_orders: { reference_prefix: string }
  portal: {
    intro_text: string | null
    footer_text: string | null
    approved_text: string
    rejected_text: string
  }
  quotes: { default_validity_days: number }
  finance: { block_delivery_with_outstanding_balance: boolean }
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
  }
  auth: {
    passwordless_code_enabled: boolean
    magic_link_enabled: boolean
  }
}

export interface SettingsPayload {
  brand_name?: string | null
  customer_contact_line?: string | null
  service_order_reference_prefix?: string | null
  portal_intro_text?: string | null
  portal_footer_text?: string | null
  portal_approved_text?: string | null
  portal_rejected_text?: string | null
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
  notification_footer_text?: string | null
  passwordless_code_enabled?: boolean
  magic_link_enabled?: boolean
}

export function getSettings() {
  return apiGet<{ data: CompanySettings }>('/company/settings')
}

export function updateSettings(payload: SettingsPayload) {
  return apiPut<{ data: CompanySettings }>('/company/settings', payload)
}
