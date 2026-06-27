const RESERVED_SEGMENTS = new Set([
  'login',
  'register',
  'select-company',
  'portal',
  'admin',
  'auth',
  'billing',
  // Portuguese aliases kept for safe fallback
  'cadastro',
  'selecionar-empresa',
  'convites',
  'assinatura-suspensa',
  'ordens-de-servico',
])

export function extractTenantSlug(pathname = window.location.pathname): string | null {
  const [first] = pathname.replace(/^\/+/, '').split('/')
  if (!first || RESERVED_SEGMENTS.has(first)) return null
  if (!/^[a-z0-9-]{3,64}$/.test(first)) return null
  return first
}

export function getStoredTenantSlug(): string | null {
  return localStorage.getItem('active_company_slug')
}

export function setStoredTenantSlug(slug: string | null) {
  if (slug) {
    localStorage.setItem('active_company_slug', slug)
    return
  }

  localStorage.removeItem('active_company_slug')
}

export function tenantPath(slug: string, path: string): string {
  return `/${slug}${path.startsWith('/') ? path : `/${path}`}`
}
