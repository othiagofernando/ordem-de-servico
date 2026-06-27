import { createRouter, createWebHistory, type RouteLocationNormalizedGeneric, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { ApiError } from '../api/client'
import { getPublicTenant } from '../api/tenants'
import { getStoredTenantSlug } from '../tenant'

const tenantChildren: RouteRecordRaw[] = [
  {
    path: 'dashboard',
    name: 'dashboard',
    component: () => import('../views/dashboard/DashboardView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'reports',
    name: 'reports',
    component: () => import('../views/reports/ReportsView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'customers',
    name: 'customers',
    component: () => import('../views/customers/CustomersView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'customers/create',
    name: 'customers.create',
    component: () => import('../views/customers/CustomerFormView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'customers/:id',
    name: 'customers.detail',
    component: () => import('../views/customers/CustomerDetailView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'customers/:id/edit',
    name: 'customers.edit',
    component: () => import('../views/customers/CustomerFormView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'equipments',
    name: 'equipments',
    component: () => import('../views/equipments/EquipmentsView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'equipments/create',
    name: 'equipments.create',
    component: () => import('../views/equipments/EquipmentFormView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'equipments/:id',
    name: 'equipments.detail',
    component: () => import('../views/equipments/EquipmentDetailView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'equipments/:id/edit',
    name: 'equipments.edit',
    component: () => import('../views/equipments/EquipmentFormView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'service-orders',
    name: 'service-orders',
    component: () => import('../views/serviceOrders/ServiceOrdersView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'service-orders/create',
    name: 'service-orders.create',
    component: () => import('../views/serviceOrders/CreateServiceOrderView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'service-orders/trash',
    name: 'service-orders.trash',
    component: () => import('../views/serviceOrders/ServiceOrderTrashView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'service-orders/:id',
    name: 'service-orders.detail',
    component: () => import('../views/serviceOrders/ServiceOrderDetailView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'members',
    name: 'members',
    component: () => import('../views/members/MembersView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'settings',
    name: 'settings',
    component: () => import('../views/settings/SettingsView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'settings/billing',
    name: 'billing.plan',
    component: () => import('../views/billing/PlanView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'checklists',
    name: 'checklists',
    component: () => import('../views/checklists/ChecklistTemplatesView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'audit',
    name: 'audit',
    component: () => import('../views/audit/AuditView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'parts',
    name: 'parts',
    component: () => import('../views/parts/PartsView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'parts/create',
    name: 'parts.create',
    component: () => import('../views/parts/PartFormView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'parts/:id/edit',
    name: 'parts.edit',
    component: () => import('../views/parts/PartFormView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'ratings',
    name: 'ratings',
    component: () => import('../views/ratings/RatingsView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'help',
    name: 'learning',
    component: () => import('../views/learning/PlaybooksView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'help/:id',
    name: 'learning.detail',
    component: () => import('../views/learning/PlaybookDetailView.vue'),
    meta: { tenantOnly: true },
  },
  {
    path: 'account',
    name: 'account',
    component: () => import('../views/account/AccountView.vue'),
    meta: { tenantOnly: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      redirect: () => {
        try {
          const slug = getStoredTenantSlug()
          return slug ? `/${slug}/dashboard` : '/login'
        } catch {
          return '/login'
        }
      },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/auth/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../views/auth/RegisterView.vue'),
      meta: { guest: true },
    },
    {
      path: '/select-company',
      name: 'select-company',
      component: () => import('../views/auth/SelectCompanyView.vue'),
      meta: { guest: true },
    },
    {
      path: '/admin',
      component: () => import('../layouts/AppLayout.vue'),
      meta: { requiresAuth: true, superAdminOnly: true },
      children: [
        {
          path: '',
          name: 'admin',
          component: () => import('../views/admin/AdminOverviewView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'companies',
          name: 'admin.companies',
          component: () => import('../views/admin/AdminCompaniesView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'companies/create',
          name: 'admin.companies.create',
          component: () => import('../views/admin/AdminCompanyFormView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'audit',
          name: 'admin.audit',
          component: () => import('../views/admin/AdminAuditView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'audit/:id',
          name: 'admin.audit.show',
          component: () => import('../views/admin/AdminAuditDetailView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'plans',
          name: 'admin.plans',
          component: () => import('../views/admin/AdminPlansView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'plans/create',
          name: 'admin.plans.create',
          component: () => import('../views/admin/AdminPlanFormView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'subscriptions',
          name: 'admin.subscriptions',
          component: () => import('../views/admin/AdminSubscriptionsView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'subscriptions/company/:id/edit',
          name: 'admin.subscriptions.edit',
          component: () => import('../views/admin/AdminSubscriptionFormView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'billing',
          name: 'admin.billing',
          component: () => import('../views/admin/AdminBillingView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'billing/:id',
          name: 'admin.billing.show',
          component: () => import('../views/admin/AdminBillingDetailView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'alerts',
          name: 'admin.alerts',
          component: () => import('../views/admin/AdminAlertsView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'companies/:id',
          name: 'admin.company',
          component: () => import('../views/admin/AdminCompanyDetailView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'companies/:id/edit',
          name: 'admin.companies.edit',
          component: () => import('../views/admin/AdminCompanyFormView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'companies/:id/history',
          name: 'admin.company.audit',
          component: () => import('../views/admin/AdminCompanyAuditView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'plans/:id/edit',
          name: 'admin.plans.edit',
          component: () => import('../views/admin/AdminPlanFormView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'users',
          name: 'admin.users',
          component: () => import('../views/admin/AdminUsersView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'roles',
          name: 'admin.roles',
          component: () => import('../views/admin/AdminRolesView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'permissions',
          name: 'admin.permissions',
          component: () => import('../views/admin/AdminPermissionsView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'playbooks',
          name: 'admin.playbooks',
          component: () => import('../views/admin/AdminPlaybooksView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'playbooks/create',
          name: 'admin.playbooks.create',
          component: () => import('../views/admin/AdminPlaybookFormView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'playbooks/:id/edit',
          name: 'admin.playbooks.edit',
          component: () => import('../views/admin/AdminPlaybookFormView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
        {
          path: 'account',
          name: 'account.global',
          component: () => import('../views/account/AccountView.vue'),
          meta: { requiresAuth: true, superAdminOnly: true },
        },
      ],
    },
    {
      path: '/:tenantSlug/login',
      name: 'tenant-login',
      component: () => import('../views/auth/LoginView.vue'),
      meta: { guest: true, tenantSlugRoute: true },
    },
    {
      path: '/:tenantSlug/auth/magic/:token',
      name: 'magic-link-consume',
      component: () => import('../views/auth/MagicLinkConsumeView.vue'),
      meta: { guest: true, tenantSlugRoute: true },
    },
    {
      path: '/:tenantSlug/invitations/:token',
      name: 'membership-invitation',
      component: () => import('../views/auth/MembershipInvitationView.vue'),
      meta: { guest: true, tenantSlugRoute: true },
    },
    {
      path: '/:tenantSlug/subscription-suspended',
      name: 'subscription-suspended',
      component: () => import('../views/billing/SubscriptionSuspendedView.vue'),
      meta: { requiresAuth: true, tenantOnly: true, tenantSlugRoute: true },
    },
    {
      path: '/:tenantSlug/company-not-found',
      name: 'company-not-found',
      component: () => import('../views/auth/CompanyNotFoundView.vue'),
      meta: { tenantSlugRoute: true },
    },
    {
      path: '/:tenantSlug',
      component: () => import('../layouts/AppLayout.vue'),
      meta: { requiresAuth: true, tenantSlugRoute: true },
      children: tenantChildren,
    },
    {
      path: '/portal/:token',
      name: 'portal',
      component: () => import('../views/portal/CustomerPortalView.vue'),
    },
    {
      path: '/portal/:token/documents/quote',
      name: 'portal.documents.quote',
      component: () => import('../views/portal/PortalDocumentView.vue'),
      meta: { documentType: 'quote' },
    },
    {
      path: '/portal/:token/documents/receipts/:paymentId',
      name: 'portal.documents.receipt',
      component: () => import('../views/portal/PortalDocumentView.vue'),
      meta: { documentType: 'receipt' },
    },
    {
      path: '/portal/:token/documents/delivery',
      name: 'portal.documents.delivery',
      component: () => import('../views/portal/PortalDocumentView.vue'),
      meta: { documentType: 'delivery_term' },
    },
    {
      path: '/:tenantSlug/service-orders/:id/print',
      name: 'service-orders.print',
      component: () => import('../views/serviceOrders/ServiceOrderPrintView.vue'),
      meta: { requiresAuth: true, tenantOnly: true, tenantSlugRoute: true },
    },
    {
      path: '/:tenantSlug/reports/print',
      name: 'reports.print',
      component: () => import('../views/reports/ReportsPrintView.vue'),
      meta: { requiresAuth: true, tenantOnly: true, tenantSlugRoute: true },
    },
    {
      path: '/:tenantSlug/service-orders/:id/documents/quote',
      name: 'service-orders.documents.quote',
      component: () => import('../views/serviceOrders/ServiceOrderDocumentPrintView.vue'),
      meta: { requiresAuth: true, tenantOnly: true, tenantSlugRoute: true, documentType: 'quote' },
    },
    {
      path: '/:tenantSlug/service-orders/:id/documents/receipts/:paymentId',
      name: 'service-orders.documents.receipt',
      component: () => import('../views/serviceOrders/ServiceOrderDocumentPrintView.vue'),
      meta: { requiresAuth: true, tenantOnly: true, tenantSlugRoute: true, documentType: 'receipt' },
    },
    {
      path: '/:tenantSlug/service-orders/:id/documents/delivery',
      name: 'service-orders.documents.delivery',
      component: () => import('../views/serviceOrders/ServiceOrderDocumentPrintView.vue'),
      meta: { requiresAuth: true, tenantOnly: true, tenantSlugRoute: true, documentType: 'delivery_term' },
    },
  ],
})

const tenantLookupCache = new Map<string, boolean>()

function hasAccessibleSubscription(auth: ReturnType<typeof useAuthStore>): boolean {
  if (!auth.subscription) return true
  if (auth.subscription.status !== 'active') return false
  return auth.subscription.days_until_expiration >= -3
}

function currentTenantSlug(to: RouteLocationNormalizedGeneric): string | null {
  return typeof to.params.tenantSlug === 'string' ? to.params.tenantSlug : null
}

function tenantTarget(auth: ReturnType<typeof useAuthStore>, name: string, params: Record<string, unknown> = {}) {
  return { name, params: { tenantSlug: auth.company?.slug, ...params } }
}

async function ensureTenantExists(slug: string): Promise<boolean> {
  if (tenantLookupCache.has(slug)) {
    return tenantLookupCache.get(slug) ?? false
  }

  try {
    await getPublicTenant(slug)
    tenantLookupCache.set(slug, true)
    return true
  } catch {
    tenantLookupCache.set(slug, false)
    return false
  }
}

router.beforeEach(async (to) => {
  try {
    const auth = useAuthStore()
    const routeTenantSlug = currentTenantSlug(to)

    if (routeTenantSlug && to.name !== 'company-not-found') {
      const exists = await ensureTenantExists(routeTenantSlug)
      if (!exists) {
        return { name: 'company-not-found', params: { tenantSlug: routeTenantSlug } }
      }
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
      return { name: 'login' }
    }

  if (to.meta.requiresAuth && auth.isAuthenticated && !auth.context) {
    try {
      await auth.loadContext()
    } catch (error) {
      if (error instanceof ApiError && error.status === 402) {
        auth.subscription = ((error.data as { subscription?: unknown })?.subscription as import('../api/auth').SubscriptionSnapshot | null) ?? null
        return auth.isSuperAdmin ? { name: 'admin' } : tenantTarget(auth, 'subscription-suspended')
      }

      auth.logout()
      return { name: 'login' }
    }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    if (!auth.context) {
      try {
        await auth.loadContext()
      } catch {
        auth.logout()
        return
      }
    }
    if (auth.isSuperAdmin) return { name: 'admin' }
    const slug = auth.company?.slug
    if (slug) return { name: 'dashboard', params: { tenantSlug: slug } }
    auth.logout()
    return
  }

  if (to.meta.superAdminOnly && !auth.isSuperAdmin) {
    return auth.isAuthenticated ? tenantTarget(auth, 'dashboard') : { name: 'login' }
  }

  if (to.meta.tenantOnly && auth.isSuperAdmin) {
    return { name: 'admin' }
  }

    if (to.meta.tenantOnly && auth.isAuthenticated && !auth.isSuperAdmin) {
      if (!routeTenantSlug || routeTenantSlug !== auth.company?.slug) {
        return routeTenantSlug
          ? { name: 'company-not-found', params: { tenantSlug: routeTenantSlug } }
          : tenantTarget(auth, 'dashboard')
      }

      if (!hasAccessibleSubscription(auth) && !['subscription-suspended', 'billing.plan'].includes(String(to.name ?? ''))) {
        return tenantTarget(auth, 'subscription-suspended')
      }
    }
  } catch (error) {
    console.error('Router guard error:', error)
    return { name: 'login' }
  }
})

export default router
