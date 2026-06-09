import { apiGet, apiPost, apiPut } from './client'

export interface CustomerContact {
  id?: number
  label: string | null
  name: string | null
  email: string | null
  phone: string | null
}

export interface CustomerAddress {
  id?: number
  label: string | null
  recipient: string | null
  line_1: string
  line_2: string | null
  district: string | null
  city: string | null
  state: string | null
  postal_code: string | null
}

export interface Customer {
  id: number
  customer_type: 'PF' | 'PJ'
  name: string
  trade_name: string | null
  document: string | null
  notes: string | null
  contacts: CustomerContact[]
  addresses: CustomerAddress[]
}

export interface CustomerPayload {
  customer_type: 'PF' | 'PJ'
  name: string
  trade_name?: string | null
  document?: string | null
  notes?: string | null
  confirm_duplicates?: boolean
  contacts?: Omit<CustomerContact, 'id'>[]
  addresses?: Omit<CustomerAddress, 'id'>[]
}

export function listCustomers(q?: string) {
  const qs = q ? `?q=${encodeURIComponent(q)}` : ''
  return apiGet<{ data: Customer[] }>(`/customers${qs}`)
}

export function getCustomer(id: number) {
  return apiGet<{ data: Customer }>(`/customers/${id}`)
}

export function createCustomer(payload: CustomerPayload) {
  return apiPost<{ data: Customer }>('/customers', payload)
}

export function updateCustomer(id: number, payload: CustomerPayload) {
  return apiPut<{ data: Customer }>(`/customers/${id}`, payload)
}
