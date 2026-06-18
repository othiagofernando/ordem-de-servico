import { apiGet, apiPost, apiPut, apiPostForm, API_BASE } from './client'

export interface EquipmentEvidence {
  id: number
  equipment_id: number
  file_name: string
  mime_type: string
  file_size_bytes: number
  caption: string | null
  uploaded_by_membership_id: number | null
  created_at: string | null
}

export interface ServiceOrderSummary {
  id: number
  order_number: string | null
  title: string | null
  status: string
  opened_at: string | null
}

export interface Equipment {
  id: number
  customer_id: number
  customer_name: string | null
  category: string
  brand: string | null
  model: string | null
  serial_number: string | null
  accessories: string | null
  notes: string | null
  serial_overrides: Array<{ id: number; serial_number: string; reason: string | null }>
  evidences: EquipmentEvidence[]
  service_orders_history: ServiceOrderSummary[]
}

export interface EquipmentPayload {
  customer_id: number
  category: string
  brand?: string | null
  model?: string | null
  serial_number?: string | null
  accessories?: string | null
  notes?: string | null
  override_serial_uniqueness?: boolean
  override_reason?: string | null
}

export interface PaginationMeta {
  total: number
  per_page: number
  current_page: number
  last_page: number
  from: number
  to: number
}

export function listEquipments(params?: { q?: string; page?: number; per_page?: number }) {
  const qs = new URLSearchParams()
  if (params?.q) qs.set('q', params.q)
  if (params?.page) qs.set('page', String(params.page))
  if (params?.per_page) qs.set('per_page', String(params.per_page))
  const query = qs.toString() ? `?${qs.toString()}` : ''
  return apiGet<{ data: Equipment[]; meta: PaginationMeta }>(`/equipments${query}`)
}

export function getEquipment(id: number) {
  return apiGet<{ data: Equipment }>(`/equipments/${id}`)
}

export function createEquipment(payload: EquipmentPayload) {
  return apiPost<{ data: Equipment }>('/equipments', payload)
}

export function updateEquipment(id: number, payload: EquipmentPayload) {
  return apiPut<{ data: Equipment }>(`/equipments/${id}`, payload)
}

export function listEquipmentEvidences(equipmentId: number) {
  return apiGet<{ data: EquipmentEvidence[] }>(`/equipments/${equipmentId}/evidences`)
}

export function uploadEquipmentEvidence(equipmentId: number, file: File, caption?: string) {
  const form = new FormData()
  form.append('file', file)
  if (caption) form.append('caption', caption)
  return apiPostForm<{ data: EquipmentEvidence }>(`/equipments/${equipmentId}/evidences`, form)
}

export function equipmentEvidenceDownloadUrl(equipmentId: number, evidenceId: number) {
  return `${API_BASE}/equipments/${equipmentId}/evidences/${evidenceId}/download`
}
