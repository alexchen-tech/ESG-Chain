import http from '@/api/http'

export interface TradeGood {
  id: string
  name: string
  product_code: string | null
  model_no: string | null
  hs_code: string
  description: string | null
  unit: string | null
  unit_price: number | null
  currency: string
  embedded_emissions: number | null
  is_cbam_applicable: boolean
  cbam_category: string | null
  is_eudr_applicable: boolean
  upstream_compliance_status: 'valid' | 'expiring_soon' | 'expired' | 'missing' | 'unconfigured'
  supplier_count: number
}

export interface TradeGoodSupplierDoc {
  doc_type: string
  status: 'valid' | 'expiring_soon' | 'expired' | 'missing'
  expires_at: string | null
}

export interface TradeGoodSupplier {
  id: string
  supplier_id: string
  supplier_name: string
  material_group: string | null
  notes: string | null
  status: string
  doc_statuses: TradeGoodSupplierDoc[]
}

export interface EmissionReport {
  id: string
  trade_good_id: string
  supplier_id: string
  supplier: { id: string; name: string; supplier_code: string }
  emissions_value: number
  calculation_note: string | null
  reported_at: string
  confirmed_at: string | null
}

export interface PortalTradeGood {
  id: string
  name: string
  product_code: string | null
  hs_code: string
  is_cbam_applicable: boolean
  cbam_category: string | null
  reported: boolean
  latest_emissions: number | null
  reported_at: string | null
  confirmed_at: string | null
}

export const tradeGoodApi = {
  list: () =>
    http.get<{ success: boolean; data: TradeGood[] }>('/api/v1/trade-goods'),

  create: (payload: Partial<TradeGood>) =>
    http.post<{ success: boolean; data: TradeGood; message: string }>('/api/v1/trade-goods', payload),

  update: (id: string, payload: Partial<TradeGood>) =>
    http.put<{ success: boolean; data: TradeGood; message: string }>(`/api/v1/trade-goods/${id}`, payload),

  destroy: (id: string) =>
    http.delete<{ success: boolean; message: string }>(`/api/v1/trade-goods/${id}`),

  suppliers: (id: string) =>
    http.get<{ success: boolean; data: TradeGoodSupplier[] }>(`/api/v1/trade-goods/${id}/suppliers`),

  addSupplier: (id: string, payload: { supplier_id: string; material_group_id?: string; notes?: string }) =>
    http.post<{ success: boolean; data: TradeGoodSupplier; message: string }>(`/api/v1/trade-goods/${id}/suppliers`, payload),

  removeSupplier: (tradeGoodId: string, supplierId: string) =>
    http.delete<{ success: boolean; message: string }>(`/api/v1/trade-goods/${tradeGoodId}/suppliers/${supplierId}`),

  emissionReports: (id: string) =>
    http.get<{ success: boolean; data: EmissionReport[] }>(`/api/v1/trade-goods/${id}/emission-reports`),

  confirmEmission: (tradeGoodId: string, emissionId: string) =>
    http.post<{ success: boolean; message: string; data: { embedded_emissions: number } }>(
      `/api/v1/trade-goods/${tradeGoodId}/emission-reports/${emissionId}/confirm`
    ),

  cbamSummary: () =>
    http.get<{ success: boolean; data: any[] }>('/api/v1/trade-goods/cbam-summary'),

  exportLinks: (id: string) =>
    http.get<{ success: boolean; data: any[] }>(`/api/v1/trade-goods/${id}/export-links`),
}

export interface DocComplianceResult {
  doc_type: string
  is_mandatory: boolean
  status: 'valid' | 'expiring_soon' | 'expired' | 'missing'
  expires_at: string | null
  supplier_name: string | null
}

export interface MarketComplianceResult {
  market: string
  required: { doc_type: string; is_mandatory: boolean }[]
  results: DocComplianceResult[]
  overall: 'pass' | 'warning' | 'fail'
}

export const marketComplianceApi = {
  batch: (market: string, tradeGoodIds: string[]) =>
    http.post<{ data: Record<string, MarketComplianceResult> }>('/api/v1/trade-goods/market-compliance-batch', {
      market,
      trade_good_ids: tradeGoodIds,
    }),
}

export const portalTradeGoodApi = {
  list: () =>
    http.get<{ success: boolean; data: PortalTradeGood[] }>('/api/v1/portal/trade-goods'),

  reportEmission: (id: string, payload: { emissions_value: number; calculation_note?: string }) =>
    http.post<{ success: boolean; data: EmissionReport; message: string }>(
      `/api/v1/portal/trade-goods/${id}/emissions`, payload
    ),
}
