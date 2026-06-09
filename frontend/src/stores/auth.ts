import { defineStore } from 'pinia'
import { apiGet, apiPost } from '../api/client'
import {
  login as apiLogin,
  selectCompany as apiSelectCompany,
  verifyPasswordlessCode,
  consumeMagicLink,
} from '../api/auth'
import type { LoginMultiResult } from '../api/auth'

interface TenantContext {
  company: { id: number; uuid: string; name: string } | null
  membership: { id: number; role: string; status: string } | null
  user: { id: number; name: string; email: string } | null
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('access_token') as string | null,
    context: null as TenantContext | null,
    pendingSelection: null as LoginMultiResult | null,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    user: (state) => state.context?.user ?? null,
    company: (state) => state.context?.company ?? null,
    membership: (state) => state.context?.membership ?? null,
    role: (state) => state.context?.membership?.role ?? null,
  },

  actions: {
    async login(email: string, password: string) {
      this.loading = true
      try {
        const result = await apiLogin(email, password)

        if ('next_step' in result && result.next_step === 'select_company') {
          this.pendingSelection = result
          return 'select_company' as const
        }

        const single = result as import('../api/auth').LoginSingleResult
        this.token = single.access_token
        localStorage.setItem('access_token', single.access_token)
        await this.loadContext()
        return 'authenticated' as const
      } finally {
        this.loading = false
      }
    },

    async selectCompany(companyUuid: string) {
      if (!this.pendingSelection) throw new Error('No pending selection')
      this.loading = true
      try {
        const result = await apiSelectCompany(this.pendingSelection.selection_token, companyUuid)
        this.token = result.access_token
        this.pendingSelection = null
        localStorage.setItem('access_token', result.access_token)
        await this.loadContext()
      } finally {
        this.loading = false
      }
    },

    async loadContext() {
      const res = await apiGet<TenantContext>('/tenant/context')
      this.context = res
    },

    async loginWithCode(email: string, company_uuid: string, code: string) {
      this.loading = true
      try {
        const result = await verifyPasswordlessCode(email, company_uuid, code)
        this.token = result.access_token
        localStorage.setItem('access_token', result.access_token)
        await this.loadContext()
        return 'authenticated' as const
      } finally {
        this.loading = false
      }
    },

    async loginWithMagicLink(token: string) {
      this.loading = true
      try {
        const result = await consumeMagicLink(token)
        this.token = result.access_token
        localStorage.setItem('access_token', result.access_token)
        await this.loadContext()
        return 'authenticated' as const
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        await apiPost('/auth/logout')
      } catch {
        // token may already be invalid — ignore
      } finally {
        this.token = null
        this.context = null
        this.pendingSelection = null
        localStorage.removeItem('access_token')
      }
    },
  },
})
