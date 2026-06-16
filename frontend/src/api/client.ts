const apiBase = (import.meta.env.VITE_API_BASE_URL || 'http://ordem-de-servico.test').replace(/\/$/, '')

export const API_BASE = `${apiBase}/api/v1`

export class ApiError extends Error {
  status: number
  data: unknown

  constructor(status: number, data: unknown, message: string) {
    super(message)
    this.status = status
    this.data = data
  }
}

function getToken(): string | null {
  return localStorage.getItem('access_token')
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

  const res = await fetch(`${API_BASE}${path}`, { ...options, headers })

  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    throw new ApiError(res.status, data, (data as { message?: string }).message ?? `HTTP ${res.status}`)
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
    },
  })

  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    throw new ApiError(res.status, data, (data as { message?: string }).message ?? `HTTP ${res.status}`)
  }

  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  anchor.click()
  URL.revokeObjectURL(url)
}
