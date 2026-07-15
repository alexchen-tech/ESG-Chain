import http from '@/api/http'

export interface DisclosureRecord {
  period_year: number
  numeric_value: number | null
  boolean_value: boolean | null
  source: 'saq_sync' | 'manual' | 'erp_sync'
  source_saq_id: string | null
  updated_at: string
}

export interface DisclosureFieldKpi {
  slug: string
  label: string
  data_type: 'boolean' | 'numeric'
  unit: string | null
  records: DisclosureRecord[]
}

export const portalDisclosureApi = {
  list: () =>
    http.get<{ success: boolean; data: DisclosureFieldKpi[] }>('/api/v1/portal/disclosures'),

  save: (payload: { field_slug: string; period_year: number; value: number | boolean }) =>
    http.post<{ success: boolean; overwritten_saq_sync: boolean }>('/api/v1/portal/disclosures', payload),
}
