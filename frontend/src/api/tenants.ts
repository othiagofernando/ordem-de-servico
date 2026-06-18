export interface PublicTenant {
  id: number
  uuid: string
  name: string
  slug: string
}

const rootApiBase = (import.meta.env.VITE_API_BASE_URL || 'http://ordem-de-servico.test').replace(/\/$/, '')

export async function getPublicTenant(slug: string) {
  const res = await fetch(`${rootApiBase}/api/v1/public/tenants/${slug}`, {
    headers: { Accept: 'application/json' },
  })

  if (!res.ok) {
    throw new Error('Empresa não encontrada.')
  }

  return res.json() as Promise<{ data: PublicTenant }>
}
