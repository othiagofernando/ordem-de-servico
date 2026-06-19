import { apiGet, apiPost, apiPut, apiDelete } from './client'
import type { Playbook, PlaybookPayload } from './playbooks'

export function adminListPlaybooks(params?: { q?: string; category?: string; status?: string }): Promise<{ data: Playbook[] }> {
  const qs = new URLSearchParams()
  if (params?.q) qs.set('q', params.q)
  if (params?.category) qs.set('category', params.category)
  if (params?.status) qs.set('status', params.status)
  const query = qs.toString()
  return apiGet(`/admin/playbooks${query ? `?${query}` : ''}`)
}

export function adminGetPlaybook(id: number): Promise<{ data: Playbook }> {
  return apiGet(`/admin/playbooks/${id}`)
}

export function adminCreatePlaybook(payload: PlaybookPayload): Promise<{ data: Playbook }> {
  return apiPost('/admin/playbooks', payload)
}

export function adminUpdatePlaybook(id: number, payload: PlaybookPayload): Promise<{ data: Playbook }> {
  return apiPut(`/admin/playbooks/${id}`, payload)
}

export function adminDeletePlaybook(id: number): Promise<void> {
  return apiDelete(`/admin/playbooks/${id}`)
}
