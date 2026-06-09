import { apiPost } from './client'

export interface LoginSingleResult {
  token_type: string
  access_token: string
  expires_at: string | null
  company: { uuid: string; name: string }
  membership: { id: number; role: string; status: string }
}

export interface LoginMultiResult {
  next_step: 'select_company'
  selection_token: string
  companies: Array<{ membership_id: number; company_uuid: string; company_name: string; role: string }>
}

export type LoginResult = LoginSingleResult | LoginMultiResult

export function login(email: string, password: string) {
  return apiPost<LoginResult>('/auth/login', { email, password })
}

export function selectCompany(selection_token: string, company_uuid: string) {
  return apiPost<LoginSingleResult>('/auth/select-company', { selection_token, company_uuid })
}

export interface PasswordlessCompany {
  uuid: string
  name: string
  passwordless_code_enabled: boolean
  magic_link_enabled: boolean
}

export function lookupPasswordlessCompanies(email: string) {
  return apiPost<{ companies: PasswordlessCompany[] }>('/auth/passwordless/lookup', { email })
}

export function requestPasswordlessCode(email: string, company_uuid: string) {
  return apiPost<{ message: string }>('/auth/passwordless/code/request', { email, company_uuid })
}

export function verifyPasswordlessCode(email: string, company_uuid: string, code: string) {
  return apiPost<LoginSingleResult>('/auth/passwordless/code/verify', { email, company_uuid, code })
}

export function requestMagicLink(email: string, company_uuid: string) {
  return apiPost<{ message: string }>('/auth/passwordless/magic-link/request', { email, company_uuid })
}

export function consumeMagicLink(token: string) {
  return apiPost<LoginSingleResult>('/auth/passwordless/magic-link/consume', { token })
}
