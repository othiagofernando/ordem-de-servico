import { apiGet, apiPost, apiPut, apiPatch, apiDelete } from './client'

export interface Part {
  id: number
  name: string
  description: string | null
  category: string | null
  sku: string | null
  unit: string
  stock_quantity: number
  min_stock_quantity: number
  cost_price_cents: number
  sale_price_cents: number
  active: boolean
  is_low_stock: boolean
  created_at: string
}

export interface ServiceOrderPart {
  id: number
  part_id: number
  part_name: string | null
  part_sku: string | null
  part_unit: string | null
  quantity: number
  unit_cost_price_cents: number
  unit_sale_price_cents: number
  notes: string | null
}

export interface PartPayload {
  name: string
  description?: string | null
  category?: string | null
  sku?: string | null
  unit?: string
  stock_quantity?: number
  min_stock_quantity?: number
  cost_price_cents?: number
  sale_price_cents?: number
  active?: boolean
}

export interface PartListParams {
  q?: string
  category?: string
  low_stock?: boolean
  active?: boolean | 'all'
  per_page?: number
  page?: number
}

export interface PaginationMeta {
  total: number
  per_page: number
  current_page: number
  last_page: number
  from: number
  to: number
}

export function listParts(params?: PartListParams): Promise<{ data: Part[]; meta: PaginationMeta }> {
  const q = new URLSearchParams()
  if (params?.q) q.set('q', params.q)
  if (params?.category) q.set('category', params.category)
  if (params?.low_stock) q.set('low_stock', '1')
  if (params?.active !== undefined && params.active !== 'all') {
    q.set('active', params.active ? '1' : '0')
  }
  if (params?.per_page) q.set('per_page', String(params.per_page))
  if (params?.page) q.set('page', String(params.page))
  const qs = q.toString()
  return apiGet(`/parts${qs ? `?${qs}` : ''}`)
}

export function getPart(id: number): Promise<{ data: Part }> {
  return apiGet(`/parts/${id}`)
}

export function createPart(payload: PartPayload): Promise<{ data: Part }> {
  return apiPost(`/parts`, payload)
}

export function updatePart(id: number, payload: PartPayload): Promise<{ data: Part }> {
  return apiPut(`/parts/${id}`, payload)
}

export function deletePart(id: number): Promise<void> {
  return apiDelete(`/parts/${id}`)
}

export function adjustStock(
  id: number,
  payload: { quantity: number; notes?: string },
): Promise<{ data: Part }> {
  return apiPatch(`/parts/${id}/stock`, payload)
}

export function addPartToServiceOrder(
  serviceOrderId: number,
  payload: { part_id: number; quantity: number; notes?: string },
): Promise<{ data: ServiceOrderPart }> {
  return apiPost(`/service-orders/${serviceOrderId}/parts`, payload)
}

export function removePartFromServiceOrder(
  serviceOrderId: number,
  serviceOrderPartId: number,
): Promise<void> {
  return apiDelete(`/service-orders/${serviceOrderId}/parts/${serviceOrderPartId}`)
}
