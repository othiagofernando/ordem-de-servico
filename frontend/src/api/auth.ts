import { API_BASE, apiPost } from './client'

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

export interface MembershipInvitation {
  company: { uuid: string; name: string }
  user: { name: string; email: string }
  membership: { id: number; role: string; status: string }
  requires_password_setup: boolean
  expires_at: string | null
}

export function fetchInvitation(token: string) {
  return fetch(`${API_BASE}/auth/invitations/${token}`, {
    headers: { Accept: 'application/json' },
  }).then(async (res) => {
    if (!res.ok) {
      const data = await res.json().catch(() => ({}))
      throw new Error((data as { message?: string }).message ?? 'Erro ao carregar convite.')
    }
    return res.json() as Promise<{ data: MembershipInvitation }>
  })
}

export function acceptInvitation(
  token: string,
  payload?: { name?: string; password?: string; password_confirmation?: string },
) {
  return apiPost<LoginSingleResult & { user: { name: string; email: string } }>(`/auth/invitations/${token}/accept`, payload)
}
