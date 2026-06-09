import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      redirect: '/dashboard',
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/auth/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/selecionar-empresa',
      name: 'select-company',
      component: () => import('../views/auth/SelectCompanyView.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      component: () => import('../layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: 'dashboard',
          name: 'dashboard',
          component: () => import('../views/dashboard/DashboardView.vue'),
        },
        {
          path: 'relatorios',
          name: 'reports',
          component: () => import('../views/reports/ReportsView.vue'),
        },
        {
          path: 'clientes',
          name: 'customers',
          component: () => import('../views/customers/CustomersView.vue'),
        },
        {
          path: 'clientes/novo',
          name: 'customers.create',
          component: () => import('../views/customers/CustomerFormView.vue'),
        },
        {
          path: 'clientes/:id',
          name: 'customers.detail',
          component: () => import('../views/customers/CustomerDetailView.vue'),
        },
        {
          path: 'clientes/:id/editar',
          name: 'customers.edit',
          component: () => import('../views/customers/CustomerFormView.vue'),
        },
        {
          path: 'equipamentos',
          name: 'equipments',
          component: () => import('../views/equipments/EquipmentsView.vue'),
        },
        {
          path: 'equipamentos/novo',
          name: 'equipments.create',
          component: () => import('../views/equipments/EquipmentFormView.vue'),
        },
        {
          path: 'equipamentos/:id',
          name: 'equipments.detail',
          component: () => import('../views/equipments/EquipmentDetailView.vue'),
        },
        {
          path: 'equipamentos/:id/editar',
          name: 'equipments.edit',
          component: () => import('../views/equipments/EquipmentFormView.vue'),
        },
        {
          path: 'ordens-de-servico',
          name: 'service-orders',
          component: () => import('../views/serviceOrders/ServiceOrdersView.vue'),
        },
        {
          path: 'ordens-de-servico/nova',
          name: 'service-orders.create',
          component: () => import('../views/serviceOrders/CreateServiceOrderView.vue'),
        },
        {
          path: 'ordens-de-servico/:id',
          name: 'service-orders.detail',
          component: () => import('../views/serviceOrders/ServiceOrderDetailView.vue'),
        },
        {
          path: 'membros',
          name: 'members',
          component: () => import('../views/members/MembersView.vue'),
        },
        {
          path: 'configuracoes',
          name: 'settings',
          component: () => import('../views/settings/SettingsView.vue'),
        },
        {
          path: 'checklists',
          name: 'checklists',
          component: () => import('../views/checklists/ChecklistTemplatesView.vue'),
        },
      ],
    },
    {
      path: '/auth/magic/:token',
      name: 'magic-link-consume',
      component: () => import('../views/auth/MagicLinkConsumeView.vue'),
      meta: { guest: true },
    },
    {
      path: '/convites/:token',
      name: 'membership-invitation',
      component: () => import('../views/auth/MembershipInvitationView.vue'),
      meta: { guest: true },
    },
    {
      path: '/portal/:token',
      name: 'portal',
      component: () => import('../views/portal/CustomerPortalView.vue'),
    },
    {
      path: '/ordens-de-servico/:id/imprimir',
      name: 'service-orders.print',
      component: () => import('../views/serviceOrders/ServiceOrderPrintView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/ordens-de-servico/:id/documentos/orcamento',
      name: 'service-orders.documents.quote',
      component: () => import('../views/serviceOrders/ServiceOrderDocumentPrintView.vue'),
      meta: { requiresAuth: true, documentType: 'quote' },
    },
    {
      path: '/ordens-de-servico/:id/documentos/recibos/:paymentId',
      name: 'service-orders.documents.receipt',
      component: () => import('../views/serviceOrders/ServiceOrderDocumentPrintView.vue'),
      meta: { requiresAuth: true, documentType: 'receipt' },
    },
    {
      path: '/ordens-de-servico/:id/documentos/termo-entrega',
      name: 'service-orders.documents.delivery',
      component: () => import('../views/serviceOrders/ServiceOrderDocumentPrintView.vue'),
      meta: { requiresAuth: true, documentType: 'delivery_term' },
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.requiresAuth && auth.isAuthenticated && !auth.context) {
    try {
      await auth.loadContext()
    } catch {
      auth.logout()
      return { name: 'login' }
    }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
