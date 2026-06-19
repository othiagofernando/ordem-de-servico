import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  getNotifications,
  markNotificationRead,
  markAllNotificationsRead,
  type AppNotification,
} from '../api/notifications'

export const useNotificationsStore = defineStore('notifications', () => {
  const items = ref<AppNotification[]>([])
  const unreadCount = ref(0)
  const loading = ref(false)
  let pollInterval: ReturnType<typeof setInterval> | null = null

  async function fetch() {
    loading.value = true
    try {
      const res = await getNotifications()
      items.value = res.data
      unreadCount.value = res.meta.unread_count
    } catch {
      // silent — polling should not crash the app
    } finally {
      loading.value = false
    }
  }

  async function readOne(id: number) {
    await markNotificationRead(id)
    const n = items.value.find((item) => item.id === id)
    if (n && !n.read_at) {
      n.read_at = new Date().toISOString()
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    }
  }

  async function readAll() {
    await markAllNotificationsRead()
    items.value.forEach((n) => {
      if (!n.read_at) n.read_at = new Date().toISOString()
    })
    unreadCount.value = 0
  }

  function startPolling() {
    fetch()
    pollInterval = setInterval(fetch, 30_000)
  }

  function stopPolling() {
    if (pollInterval) {
      clearInterval(pollInterval)
      pollInterval = null
    }
  }

  return { items, unreadCount, loading, fetch, readOne, readAll, startPolling, stopPolling }
})
