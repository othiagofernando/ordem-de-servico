import { apiGet } from './client'

export interface CepData {
  street?: string
  district?: string
  city?: string
  state?: string
}

export async function fetchCepData(cep: string): Promise<CepData | null> {
  const digits = cep.replace(/\D/g, '')
  if (digits.length !== 8) return null

  try {
    const data = await apiGet<CepData>(`/cep/${digits}`)
    return data
  } catch (error) {
    console.error('Error fetching CEP data:', error)
    return null
  }
}
