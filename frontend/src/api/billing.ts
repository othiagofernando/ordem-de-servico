import { apiGet, apiPost } from './client'

export interface BillingPlan {
  id: number
  slug: string
  name: string
  limits: {
    max_users: number | null
    max_service_orders_per_month: number | null
    max_attachments_storage_mb: number | null
  }
  reference_price_cents: number | null
}

export interface BillingInvoice {
  id: number
  status: string
  amount_cents: number
  currency: string
  due_at: string | null
  paid_at: string | null
  invoice_url: string | null
  pdf_url: string | null
}

export interface BillingSubscriptionData {
  data: import('./auth').SubscriptionSnapshot | null
  plans: BillingPlan[]
  invoices: BillingInvoice[]
}

export function getSubscriptionBilling() {
  return apiGet<BillingSubscriptionData>('/subscription')
}

export function checkoutBilling(planId: number) {
  return apiPost<{ data: {
    subscription_id: number
    checkout_url: string | null
    invoice_url: string | null
    pdf_url: string | null
    payment_hint: string | null
    subscription_status: string
  } }>(
    '/subscription/checkout',
    { plan_id: planId },
  )
}

export function cancelBillingSubscription(confirmation: string) {
  return apiPost<{ data: { status: string } }>('/subscription/cancel', { confirmation })
}
