import { defineStore } from 'pinia'
import { apiPost } from '../api/client'
import {
  getAuthBootstrap,
  login as apiLogin,
  register as apiRegister,
  selectCompany as apiSelectCompany,
  verifyPasswordlessCode,
  consumeMagicLink,
  type RegisterPayload,
  type BootstrapMembership,
  type SubscriptionSnapshot,
  type TenantContext,
} from '../api/auth'
import type { LoginMultiResult } from '../api/auth'
import { setStoredTenantSlug } from '../tenant'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('access_token') as string | null,
    context: null as TenantContext | null,
    memberships: [] as BootstrapMembership[],
    permissions: [] as string[],
    subscription: null as SubscriptionSnapshot | null,
    pendingSelection: null as LoginMultiResult | null,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    user: (state) => state.context?.user ?? null,
    company: (state) => state.context?.company ?? null,
    membership: (state) => state.context?.membership ?? null,
    role: (state) => state.context?.membership?.role ?? null,
    membershipCount: (state) => state.memberships.length,
    isSuperAdmin: (state) => state.context?.user?.super_admin ?? false,
    subscriptionExpiringSoon: (state) => (state.subscription?.days_until_expiration ?? 999) <= 7,
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

    async register(payload: RegisterPayload) {
      this.loading = true
      try {
        const result = await apiRegister(payload)
        this.token = result.access_token
        localStorage.setItem('access_token', result.access_token)
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
      const res = await getAuthBootstrap()
      this.context = res.context
      this.memberships = res.memberships
      this.permissions = res.permissions
      this.subscription = res.subscription
      setStoredTenantSlug(res.context.company?.slug ?? null)
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
        this.memberships = []
        this.permissions = []
        this.subscription = null
        this.pendingSelection = null
        localStorage.removeItem('access_token')
        setStoredTenantSlug(null)
      }
    },
  },
})
