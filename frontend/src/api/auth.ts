import { API_BASE, apiGet, apiPost } from './client'

export interface LoginSingleResult {
  token_type: string
  access_token: string
  expires_at: string | null
  company: { uuid: string; name: string; slug?: string } | null
  membership: { id: number; role: string; status: string } | null
  user?: { id: number; name: string; email: string; super_admin: boolean }
}

export interface LoginMultiResult {
  next_step: 'select_company'
  selection_token: string
  companies: Array<{ membership_id: number; company_uuid: string; company_name: string; company_slug?: string; role: string }>
}

export type LoginResult = LoginSingleResult | LoginMultiResult

export function login(email: string, password: string) {
  return apiPost<LoginResult>('/auth/login', { email, password })
}

export interface RegisterPayload {
  company_name: string
  user_name: string
  user_email: string
  password: string
  password_confirmation: string
}

export interface RegisterResult extends LoginSingleResult {
  company: LoginSingleResult['company'] & { slug: string }
  user: { id: number; name: string; email: string; super_admin: boolean }
}

export function register(payload: RegisterPayload) {
  return apiPost<RegisterResult>('/register', payload)
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

export function recoverAccess(email: string, company_uuid: string) {
  return apiPost<{ message: string }>('/auth/recover-access', { email, company_uuid })
}

export interface MembershipInvitation {
  company: { uuid: string; name: string; slug?: string }
  user: { name: string; email: string }
  membership: { id: number; role: string; status: string }
  requires_password_setup: boolean
  expires_at: string | null
}

export interface TenantContext {
  company: { id: number; uuid: string; name: string; slug?: string } | null
  membership: { id: number; role: string; status: string } | null
  user: {
    id: number
    name: string
    email: string
    super_admin: boolean
    admin_role: string | null
    admin_role_id: number | null
    admin_permissions: string[] | null
  } | null
}

export interface BootstrapMembership {
  id: number
  company_id: number
  company_uuid: string
  company_name: string
  company_slug?: string
  role: string
  status: string
  is_active_context: boolean
}

export interface SubscriptionSnapshot {
  id: number
  status: string
  is_trial: boolean
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

export interface AuthBootstrap {
  context: TenantContext
  permissions: string[]
  subscription: SubscriptionSnapshot | null
  memberships: BootstrapMembership[]
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

export function getAuthBootstrap() {
  return apiGet<AuthBootstrap>('/auth/bootstrap')
}
