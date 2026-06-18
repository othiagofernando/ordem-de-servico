import { computed } from 'vue'
import { useRoute, type LocationQueryRaw, type RouteLocationAsRelativeGeneric, type RouteParamsRawGeneric } from 'vue-router'
import { useAuthStore } from '../stores/auth'

export function useTenantRouting() {
  const route = useRoute()
  const auth = useAuthStore()

  const tenantSlug = computed(() => {
    return typeof route.params.tenantSlug === 'string'
      ? route.params.tenantSlug
      : auth.company?.slug
  })

  function tenantRoute(
    name: string,
    options: { params?: RouteParamsRawGeneric; query?: LocationQueryRaw } = {},
  ): RouteLocationAsRelativeGeneric {
    return {
      name,
      query: options.query,
      params: {
        tenantSlug: tenantSlug.value,
        ...(options.params ?? {}),
      },
    }
  }

  function tenantLoginRoute() {
    // Após logout, auth.company é zerado, então sempre retornar para login global
    // em vez de tentar usar o tenantSlug da URL
    return auth.isAuthenticated && tenantSlug.value
      ? { name: 'tenant-login', params: { tenantSlug: tenantSlug.value } }
      : { name: 'login' }
  }

  return {
    route,
    tenantSlug,
    tenantRoute,
    tenantLoginRoute,
  }
}
