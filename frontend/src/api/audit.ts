import { apiGet } from './client'

export interface AuditLogEntry {
  id: number
  company_id: number
  actor_membership_id: number | null
  actor_name: string | null
  category: string
  event_type: string
  auditable_type: string | null
  auditable_id: number | null
  context: Record<string, unknown> | null
  before_values: Record<string, unknown> | null
  after_values: Record<string, unknown> | null
  occurred_at: string | null
}

export interface AuditLogResponse {
  data: AuditLogEntry[]
}

export function getAuditLogs(params?: {
  category?: string
  event_type?: string
}): Promise<AuditLogResponse> {
  const query = new URLSearchParams()
  if (params?.category) query.set('category', params.category)
  if (params?.event_type) query.set('event_type', params.event_type)
  const qs = query.toString()
  return apiGet<AuditLogResponse>(`/audit-logs${qs ? `?${qs}` : ''}`)
}
