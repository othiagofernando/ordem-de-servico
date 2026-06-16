import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from './client'

export interface Membership {
  id: number
  user_id: number
  role: string
  status: string
  user_name: string | null
  user_email: string | null
  user_phone: string | null
}

export function listMemberships(includeInactive = false) {
  const query = includeInactive ? '?include_inactive=1' : ''

  return apiGet<{ data: Membership[] }>(`/tenant/memberships${query}`)
}

export function inviteMembership(payload: { name: string; email: string; role: string }) {
  return apiPost<{
    data: {
      membership: { id: number; user_id: number; role: string; status: string }
      invitation: { token: string; accept_url: string; requires_password_setup: boolean }
    }
  }>('/tenant/memberships/invitations', payload)
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
