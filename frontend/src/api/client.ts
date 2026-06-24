import { extractTenantSlug, getStoredTenantSlug } from '../tenant'

const apiBase = (import.meta.env.VITE_API_BASE_URL || 'http://ordem-de-servico.test').replace(/\/$/, '')

export const API_BASE = `${apiBase}/api/v1`

export class ApiError extends Error {
  status: number
  data: unknown
  isForbidden: boolean

  constructor(status: number, data: unknown, message?: string) {
    const isForbidden = status === 403
    const finalMessage = message ?? getDefaultErrorMessage(status, isForbidden)
    super(finalMessage)
    this.status = status
    this.data = data
    this.isForbidden = isForbidden
  }
}

function getDefaultErrorMessage(status: number, isForbidden: boolean): string {
  if (isForbidden) {
    return 'Você não tem permissão para acessar este recurso'
  }
  if (status === 401) {
    return 'Sua sessão expirou. Faça login novamente'
  }
  if (status === 404) {
    return 'Recurso não encontrado'
  }
  if (status === 422) {
    return 'Dados inválidos'
  }
  if (status >= 500) {
    return 'Erro no servidor. Tente novamente mais tarde'
  }
  return `Erro na requisição (HTTP ${status})`
}

function getToken(): string | null {
  return localStorage.getItem('access_token')
}

function getTenantSlug(): string | null {
  return extractTenantSlug() ?? getStoredTenantSlug()
}

export async function apiFetch<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const token = getToken()

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...(options.headers as Record<string, string>),
  }

  if (token) {
    headers['Authorization'] = `Bearer ${token}`
  }

  const tenantSlug = getTenantSlug()
  if (tenantSlug) {
    headers['X-Tenant-Slug'] = tenantSlug
  }

  const res = await fetch(`${API_BASE}${path}`, { ...options, headers })

  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    throw new ApiError(res.status, data, (data as { message?: string }).message)
  }

  if (res.status === 204) return undefined as T

  return res.json() as Promise<T>
}

export function apiGet<T>(path: string) {
  return apiFetch<T>(path)
}

export function apiPost<T>(path: string, body?: unknown) {
  return apiFetch<T>(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined })
}

export async function apiPostForm<T>(path: string, formData: FormData): Promise<T> {
  const token = getToken()

  const headers: Record<string, string> = {
    Accept: 'application/json',
  }

  if (token) {
    headers['Authorization'] = `Bearer ${token}`
  }

  const tenantSlug = getTenantSlug()
  if (tenantSlug) {
    headers['X-Tenant-Slug'] = tenantSlug
  }

  const res = await fetch(`${API_BASE}${path}`, {
    method: 'POST',
    body: formData,
    headers,
  })

  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    throw new ApiError(res.status, data, (data as { message?: string }).message)
  }

  return res.json() as Promise<T>
}

export function apiPut<T>(path: string, body?: unknown) {
  return apiFetch<T>(path, { method: 'PUT', body: body ? JSON.stringify(body) : undefined })
}

export function apiPatch<T>(path: string, body?: unknown) {
  return apiFetch<T>(path, { method: 'PATCH', body: body ? JSON.stringify(body) : undefined })
}

export function apiDelete<T>(path: string, body?: unknown) {
  return apiFetch<T>(path, { method: 'DELETE', body: body ? JSON.stringify(body) : undefined })
}

export async function apiDownload(path: string, filename: string, accept = 'text/csv') {
  const token = getToken()
  const res = await fetch(`${API_BASE}${path}`, {
    headers: {
      Accept: accept,
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(getTenantSlug() ? { 'X-Tenant-Slug': getTenantSlug() as string } : {}),
    },
  })

  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    throw new ApiError(res.status, data, (data as { message?: string }).message)
  }

  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  anchor.click()
  URL.revokeObjectURL(url)
}
