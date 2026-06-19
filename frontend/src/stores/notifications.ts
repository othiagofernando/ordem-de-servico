import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  getNotifications,
  markNotificationRead,
  markAllNotificationsRead,
  type AppNotification,
} from '../api/notifications'
import { createEcho } from '../echo'
import type Echo from 'laravel-echo'

export const useNotificationsStore = defineStore('notifications', () => {
  const items = ref<AppNotification[]>([])
  const unreadCount = ref(0)
  const loading = ref(false)
  let echoInstance: Echo | null = null

  async function fetch() {
    loading.value = true
    try {
      const res = await getNotifications()
      items.value = res.data
      unreadCount.value = res.meta.unread_count
    } catch {
      // silent
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

  function startListening(token: string, slug: string, membershipId: number) {
    stopListening()
    fetch()

    try {
      echoInstance = createEcho(token, slug)
      echoInstance
        .private(`membership.${membershipId}`)
        .listen('.notification.created', (data: AppNotification) => {
          items.value.unshift(data)
          if (items.value.length > 30) items.value.pop()
          unreadCount.value += 1
        })
    } catch (err) {
      console.warn('[notifications] WebSocket connection failed:', err)
      echoInstance = null
    }
  }

  function stopListening() {
    if (echoInstance) {
      echoInstance.disconnect()
      echoInstance = null
    }
  }

  return { items, unreadCount, loading, fetch, readOne, readAll, startListening, stopListening }
})
