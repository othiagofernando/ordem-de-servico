import { apiGet, apiPost, apiPut } from './client'

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
