import http from '@/api/http'

export interface MarketComplianceRule {
  id: string
  market: string
  doc_type: string
  // 法規範疇（見 App\Models\MarketComplianceRule::PROGRAMS），供出口審查篩選只跑哪個範疇
  program: string
  scope: 'material' | 'product'
  is_mandatory: boolean
  effective_from: string
  notes: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface CreateMarketComplianceRulePayload {
  market: string
  doc_type: string
  program?: string
  scope?: 'material' | 'product'
  is_mandatory?: boolean
  effective_from: string
  notes?: string | null
}

export interface UpdateMarketComplianceRulePayload {
  market?: string
  doc_type?: string
  program?: string
  scope?: 'material' | 'product'
  is_mandatory?: boolean
  effective_from?: string
  notes?: string | null
  is_active?: boolean
}

export const marketComplianceRulesApi = {
  list: (params?: { market?: string; is_active?: boolean }) =>
    http.get<{ data: MarketComplianceRule[] }>('/api/v1/market-compliance-rules', { params }),

  create: (payload: CreateMarketComplianceRulePayload) =>
    http.post<{ data: MarketComplianceRule }>('/api/v1/market-compliance-rules', payload),

  update: (id: string, payload: UpdateMarketComplianceRulePayload) =>
    http.put<{ data: MarketComplianceRule }>(`/api/v1/market-compliance-rules/${id}`, payload),

  destroy: (id: string) =>
    http.delete<{ message: string }>(`/api/v1/market-compliance-rules/${id}`),
}
