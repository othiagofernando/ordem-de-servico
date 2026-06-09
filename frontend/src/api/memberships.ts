import { apiGet } from './client'

export interface Membership {
  id: number
  role: string
  status: string
  user_name: string | null
  user_email: string | null
}

export function listMemberships() {
  return apiGet<{ data: Membership[] }>('/tenant/memberships')
}
