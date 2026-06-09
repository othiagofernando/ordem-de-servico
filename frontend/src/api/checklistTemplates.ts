import { apiGet, apiPost } from './client'

export interface ChecklistTemplateItem {
  key: string
  label: string
  type: string
  required?: boolean
}

export interface ChecklistTemplate {
  id: number
  category: string
  version: number
  title: string
  items: ChecklistTemplateItem[]
}

export interface ChecklistTemplatePayload {
  category: string
  title: string
  items: ChecklistTemplateItem[]
}

export function listChecklistTemplates(category?: string) {
  const qs = category ? `?category=${encodeURIComponent(category)}` : ''
  return apiGet<{ data: ChecklistTemplate[] }>(`/intake-checklist-templates${qs}`)
}

export function createChecklistTemplate(payload: ChecklistTemplatePayload) {
  return apiPost<{ data: ChecklistTemplate }>('/intake-checklist-templates', payload)
}
