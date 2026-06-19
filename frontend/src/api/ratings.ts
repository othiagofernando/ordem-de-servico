import { apiGet } from './client'

export interface Rating {
  id: number
  rating: number
  comment: string | null
  rated_at: string
  service_order_id: number
  service_order_reference: string | null
  service_order_order_number: string | null
  service_order_title: string | null
  customer_name: string | null
  technician_name: string | null
}

export interface RatingsStats {
  total_ratings: number
  average_rating: number | null
  distribution: Record<string, number>
}

export interface RatingsListParams {
  rating?: number
  from?: string
  to?: string
  per_page?: number
  page?: number
}

export interface PaginationMeta {
  total: number
  per_page: number
  current_page: number
  last_page: number
  from: number | null
  to: number | null
}

export function listRatings(params?: RatingsListParams): Promise<{
  data: Rating[]
  meta: PaginationMeta
  stats: RatingsStats
}> {
  const q = new URLSearchParams()
  if (params?.rating) q.set('rating', String(params.rating))
  if (params?.from) q.set('from', params.from)
  if (params?.to) q.set('to', params.to)
  if (params?.per_page) q.set('per_page', String(params.per_page))
  if (params?.page) q.set('page', String(params.page))
  const qs = q.toString()
  return apiGet(`/ratings${qs ? `?${qs}` : ''}`)
}

export async function submitPortalRating(
  token: string,
  payload: { rating: number; comment?: string },
): Promise<{ data: { id: number; rating: number; comment: string | null; rated_at: string } }> {
  const res = await fetch(`${(await import('./client')).API_BASE}/portal/quotes/${token}/rating`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  })
  if (!res.ok) {
    const body = await res.json().catch(() => ({}))
    throw Object.assign(new Error(body.message ?? 'Erro ao enviar avaliação.'), { data: body, status: res.status })
  }
  return res.json()
}
