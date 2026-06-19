import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from './client'

export interface Membership {
  id: number
  user_id: number
  role: string | null
  company_role_id: number | null
  company_role_name: string | null
  status: string
  permissions: string[]
  resolved_permissions: string[]
  user_name: string | null
  user_email: string | null
  user_phone: string | null
  created_at: string | null
}

export interface CompanyRole {
  id: number
  name: string
  permissions: string[]
  memberships_count: number | null
  created_at: string | null
}

export interface RolePreset {
  role: string
  permissions: string[]
}

export function listMemberships(includeInactive = false) {
  const query = includeInactive ? '?include_inactive=1' : ''
  return apiGet<{ data: Membership[] }>(`/tenant/memberships${query}`)
}

export function listRoles() {
  return apiGet<{ data: RolePreset[] }>('/tenant/roles')
}

export function inviteMembership(payload: { name: string; email: string; role: string }) {
  return apiPost<{
    data: {
      membership: { id: number; user_id: number; role: string; status: string }
      invitation: { token: string; accept_url: string; requires_password_setup: boolean }
    }
  }>('/tenant/memberships/invitations', payload)
}

export function updateMembershipAuthorization(
  membershipId: number,
  payload: { role?: string | null; company_role_id?: number | null; permissions?: string[] },
) {
  return apiPatch<{ data: { id: number; role: string | null; company_role_id: number | null; permissions: string[] } }>(
    `/tenant/memberships/${membershipId}/authorization`,
    payload,
  )
}

export function listCompanyRoles() {
  return apiGet<{ data: CompanyRole[] }>('/tenant/company-roles')
}

export function createCompanyRole(payload: { name: string; permissions: string[] }) {
  return apiPost<{ data: CompanyRole }>('/tenant/company-roles', payload)
}

export function updateCompanyRole(id: number, payload: { name?: string; permissions?: string[] }) {
  return apiPut<{ data: CompanyRole }>(`/tenant/company-roles/${id}`, payload)
}

export function deleteCompanyRole(id: number) {
  return apiDelete<{ message: string }>(`/tenant/company-roles/${id}`)
}

export function updateMembershipStatus(membershipId: number, status: 'active' | 'suspended') {
  return apiPatch<{ data: Membership }>(`/tenant/memberships/${membershipId}/status`, { status })
}

export function removeMembership(membershipId: number, password: string) {
  return apiDelete<{ message: string }>(`/tenant/memberships/${membershipId}`, { password })
}

export function updateMemberUser(
  membershipId: number,
  data: { name: string; email: string; phone: string | null },
) {
  return apiPatch<{ message: string }>(`/tenant/memberships/${membershipId}/user`, data)
}

export function resetMemberPassword(
  membershipId: number,
  adminPassword: string,
  password: string,
  passwordConfirmation: string,
) {
  return apiPut<{ message: string }>(`/tenant/memberships/${membershipId}/user/password`, {
    admin_password: adminPassword,
    password,
    password_confirmation: passwordConfirmation,
  })
}
