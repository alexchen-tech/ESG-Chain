import http from '@/api/http'

export interface PcfRequestProgress {
  submitted: number
  total: number
}

export interface PcfRequestSummary {
  id: string
  supplier_id: string
  supplier_name: string | null
  period_start: string
  period_end: string
  due_date: string
  status: 'pending' | 'submitted' | 'verified' | 'overdue'
  saq_round_id: string | null
  progress: PcfRequestProgress
  created_at: string
}

export interface PcfRequestLine {
  id: string
  bom_line_id: string
  material_name: string
  hs_code: string | null
  status: 'pending' | 'submitted' | 'verified'
  submitted_at: string | null
}

export interface PcfRequestDetail extends PcfRequestSummary {
  days_left: number
  lines: PcfRequestLine[]
}

export interface PcfBatchItem {
  supplier_id: string
  bom_line_ids: string[]
  period_start: string
  period_end: string
  due_date: string
  saq_round_id?: string | null
}

export interface PcfRequestPagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export const pcfRequestApi = {
  list: (params?: {
    supplier_id?: string
    status?: string
    period_year?: number | string
    due_before?: string
    page?: number
    per_page?: number
  }) =>
    http.get<{ success: boolean; data: PcfRequestSummary[]; pagination: PcfRequestPagination }>('/api/v1/pcf-requests', { params }),

  batchCreate: (batches: PcfBatchItem[]) =>
    http.post<{ success: boolean; data: { created: number; skipped: any[]; errors: string[] } }>(
      '/api/v1/pcf-requests/batch',
      { batches }
    ),
}

export const portalPcfApi = {
  list: () =>
    http.get<{ success: boolean; data: PcfRequestDetail[] }>('/api/v1/portal/pcf-requests'),

  updateLine: (pcfRequestId: string, lineId: string, data: { declared_value: number; quantity_unit: string }) =>
    http.put(`/api/v1/portal/pcf-requests/${pcfRequestId}/lines/${lineId}`, data),

  submit: (pcfRequestId: string) =>
    http.post(`/api/v1/portal/pcf-requests/${pcfRequestId}/submit`),
}
