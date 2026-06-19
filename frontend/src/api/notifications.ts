import { apiGet, apiPost } from './client'

export interface AppNotification {
  id: number
  type: string
  title: string
  body: string
  action_url: string | null
  read_at: string | null
  created_at: string
}

export interface NotificationsMeta {
  unread_count: number
}

export function getNotifications(): Promise<{ data: AppNotification[]; meta: NotificationsMeta }> {
  return apiGet('/notifications')
}

export function markNotificationRead(id: number): Promise<void> {
  return apiPost(`/notifications/${id}/read`, {})
}

export function markAllNotificationsRead(): Promise<void> {
  return apiPost('/notifications/read-all', {})
}
