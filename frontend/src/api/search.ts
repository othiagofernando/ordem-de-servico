import { apiGet } from './client'

export interface SearchResult {
  type: 'service_order' | 'customer' | 'equipment' | 'member' | 'playbook' | 'part' | 'company' | 'plan' | 'admin_user'
  id: number
  label: string
  sublabel: string
  meta: string | null
}

export function search(q: string): Promise<{ data: SearchResult[] }> {
  return apiGet(`/search?q=${encodeURIComponent(q)}`)
}

export function adminSearch(q: string): Promise<{ data: SearchResult[] }> {
  return apiGet(`/admin/search?q=${encodeURIComponent(q)}`)
}
