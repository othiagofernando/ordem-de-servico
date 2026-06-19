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

export interface ImpersonationMeta {
  userName: string
  companyName: string
  companyId: number
}

function loadImpersonationMeta(): ImpersonationMeta | null {
  try {
    const raw = localStorage.getItem('impersonation')
    return raw ? (JSON.parse(raw) as ImpersonationMeta) : null
  } catch {
    return null
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('access_token') as string | null,
    impersonation: loadImpersonationMeta(),
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
    adminRole: (state) => state.context?.user?.admin_role ?? null,
    adminRoleId: (state) => state.context?.user?.admin_role_id ?? null,
    adminPermissions: (state): string[] => {
      const user = state.context?.user
      if (!user?.super_admin) return []
      // Custom role: use permissions from the role
      if (user.admin_role_id && user.admin_permissions) return user.admin_permissions
      // Fixed role: map to sections
      const role = user.admin_role ?? 'super_admin'
      if (role === 'super_admin') {
        return ['overview', 'alerts', 'companies', 'companies.manage', 'subscriptions', 'billing', 'plans', 'audit']
      }
      const map: Record<string, string[]> = {
        support: ['overview', 'alerts', 'companies', 'companies.manage', 'subscriptions', 'audit'],
        billing: ['overview', 'billing', 'subscriptions'],
        plans: ['overview', 'plans'],
        viewer: ['overview', 'companies', 'subscriptions', 'audit'],
      }
      return map[role] ?? ['overview']
    },
    subscriptionExpiringSoon: (state) => (state.subscription?.days_until_expiration ?? 999) <= 7,
    isImpersonating: (state) => state.impersonation !== null,
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
        this.impersonation = null
        localStorage.removeItem('access_token')
        localStorage.removeItem('admin_token')
        localStorage.removeItem('impersonation')
        setStoredTenantSlug(null)
      }
    },

    async startImpersonation(accessToken: string, meta: ImpersonationMeta) {
      const currentToken = this.token
      if (currentToken) {
        localStorage.setItem('admin_token', currentToken)
      }
      const metaJson = JSON.stringify(meta)
      localStorage.setItem('impersonation', metaJson)
      this.impersonation = meta
      this.token = accessToken
      localStorage.setItem('access_token', accessToken)
      await this.loadContext()
    },

    async stopImpersonation() {
      try {
        await apiPost('/auth/logout')
      } catch {
        // ignore
      }

      const adminToken = localStorage.getItem('admin_token')
      localStorage.removeItem('admin_token')
      localStorage.removeItem('impersonation')
      this.impersonation = null

      if (adminToken) {
        this.token = adminToken
        localStorage.setItem('access_token', adminToken)
        await this.loadContext()
      } else {
        this.token = null
        this.context = null
        this.memberships = []
        this.permissions = []
        this.subscription = null
        localStorage.removeItem('access_token')
        setStoredTenantSlug(null)
      }
    },
  },
})
