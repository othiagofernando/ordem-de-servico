import { apiGet, apiPut } from './client'

export interface CompanySettings {
  company_id: number
  branding: { brand_name: string }
  portal: { intro_text: string | null }
  quotes: { default_validity_days: number }
  notifications: {
    quote_available: boolean
    quote_expiring: boolean
    ready_for_pickup: boolean
    payment_receipt: boolean
  }
  auth: {
    passwordless_code_enabled: boolean
    magic_link_enabled: boolean
  }
}

export interface SettingsPayload {
  brand_name?: string | null
  portal_intro_text?: string | null
  default_quote_validity_days?: number
  notify_quote_available?: boolean
  notify_quote_expiring?: boolean
  notify_ready_for_pickup?: boolean
  notify_payment_receipt?: boolean
  passwordless_code_enabled?: boolean
  magic_link_enabled?: boolean
}

export function getSettings() {
  return apiGet<{ data: CompanySettings }>('/company/settings')
}

export function updateSettings(payload: SettingsPayload) {
  return apiPut<{ data: CompanySettings }>('/company/settings', payload)
}
