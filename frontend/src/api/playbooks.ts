import { apiGet, apiPost, apiPut, apiDelete } from './client'

export interface PlaybookStep {
  id: number
  sort_order: number
  title: string
  content: string
  tip: string | null
  tip_type: 'info' | 'warning' | 'success'
}

export interface Playbook {
  id: number
  title: string
  description: string | null
  category: string | null
  emoji: string
  status: 'draft' | 'published'
  steps_count: number
  created_at: string
  steps: PlaybookStep[]
}

export interface PlaybookStepPayload {
  title: string
  content: string
  tip: string | null
  tip_type: 'info' | 'warning' | 'success'
}

export interface PlaybookPayload {
  title: string
  description: string | null
  category: string | null
  emoji: string
  status: 'draft' | 'published'
  steps: PlaybookStepPayload[]
}

export function listPlaybooks(params?: { q?: string; category?: string; status?: string }): Promise<{ data: Playbook[] }> {
  const qs = new URLSearchParams()
  if (params?.q) qs.set('q', params.q)
  if (params?.category) qs.set('category', params.category)
  if (params?.status) qs.set('status', params.status)
  const query = qs.toString()
  return apiGet(`/playbooks${query ? `?${query}` : ''}`)
}

export function getPlaybook(id: number): Promise<{ data: Playbook }> {
  return apiGet(`/playbooks/${id}`)
}

export function createPlaybook(payload: PlaybookPayload): Promise<{ data: Playbook }> {
  return apiPost('/playbooks', payload)
}

export function updatePlaybook(id: number, payload: PlaybookPayload): Promise<{ data: Playbook }> {
  return apiPut(`/playbooks/${id}`, payload)
}

export function deletePlaybook(id: number): Promise<void> {
  return apiDelete(`/playbooks/${id}`)
}
