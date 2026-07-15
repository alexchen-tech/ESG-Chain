import http from '@/api/http'

export interface CustomerContact {
  id: string
  customer_id: string
  name: string
  email: string
  phone: string | null
  title: string | null
  is_primary: boolean
}

export interface Customer {
  id: string
  code: string
  name: string
  country_code: string
  eori_number: string | null
  vat_number: string | null
  customer_type: 'brand' | 'retailer' | 'distributor' | 'agent' | 'oem'
  address: string | null
  website: string | null
  notes: string | null
  status: 'active' | 'inactive'
  contacts?: CustomerContact[]
  contacts_count?: number
  trade_goods_count?: number
  created_at: string
}

export interface CustomerListParams {
  search?: string
  status?: string
  customer_type?: string
  per_page?: number
  page?: number
}

export const customersApi = {
  list: (params?: CustomerListParams) =>
    http.get<{ success: boolean; data: Customer[]; pagination: any }>('/api/v1/customers', { params }),

  get: (id: string) =>
    http.get<{ success: boolean; data: Customer }>(`/api/v1/customers/${id}`),

  create: (data: Partial<Customer>) =>
    http.post<{ success: boolean; data: Customer; warnings: string[] }>('/api/v1/customers', data),

  update: (id: string, data: Partial<Customer>) =>
    http.patch<{ success: boolean; data: Customer }>(`/api/v1/customers/${id}`, data),

  destroy: (id: string) =>
    http.delete<{ success: boolean; message: string }>(`/api/v1/customers/${id}`),
}

export const customerContactApi = {
  create: (customerId: string, data: Partial<CustomerContact>) =>
    http.post<{ success: boolean; data: CustomerContact }>(
      `/api/v1/customers/${customerId}/contacts`,
      data
    ),

  destroy: (customerId: string, contactId: string) =>
    http.delete<{ success: boolean; message: string }>(
      `/api/v1/customers/${customerId}/contacts/${contactId}`
    ),
}
