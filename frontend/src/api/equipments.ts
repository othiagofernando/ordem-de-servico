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

export function listEquipments(q?: string) {
  const qs = q ? `?q=${encodeURIComponent(q)}` : ''
  return apiGet<{ data: Equipment[] }>(`/equipments${qs}`)
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
