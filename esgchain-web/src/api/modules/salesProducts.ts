import http from '@/api/http'

export interface SalesProduct {
  id: string
  name: string
  product_code: string | null
  model_no: string | null
  batch_nos?: string[]
  hs_code: string
  description: string | null
  unit: string | null
  unit_price: number | null
  currency: string
  embedded_emissions: number | null
  emissions_source: string | null
  emissions_updated_at: string | null
  is_cbam_applicable: boolean
  cbam_category: string | null
  dpp_category: string | null
  is_eudr_applicable: boolean
  upstream_compliance_status: 'valid' | 'expiring_soon' | 'expired' | 'missing' | 'unconfigured'
  supplier_count: number
  customer_id: string | null
  customer_name: string | null
  applicable_regulations: string[] | null
  inferred_regulations: string[] | null
}

export interface ProductPackaging {
  id: string
  sales_product_id: string
  recycled_content_ratio: number | null
  recyclable: boolean | null
  reusable: boolean | null
  material_description: string | null
  notes: string | null
}

export interface ProductBatterySpec {
  id: string
  sales_product_id: string
  battery_category: 'portable' | 'industrial' | 'ev' | 'lmt' | null
  chemistry: string | null
  rated_capacity_ah: number | null
  rated_voltage_v: number | null
  weight_kg: number | null
  lithium_recycled_content_ratio: number | null
  cobalt_recycled_content_ratio: number | null
  nickel_recycled_content_ratio: number | null
  lead_recycled_content_ratio: number | null
  cycle_life: number | null
  expected_lifetime_years: number | null
  discharge_efficiency_ratio: number | null
  initial_capacity_soh_note: string | null
  operating_temp_range: string | null
}

export interface SalesProductSupplier {
  id: string
  supplier_id: string
  supplier_name: string
  material_group: string | null
  supplier_facility_id: string | null
  supplier_facility_name: string | null
  facility_type: string | null
  notes: string | null
  status: string
  doc_statuses: { doc_type: string; status: string; expires_at: string | null }[]
}

export interface BomLine {
  id: string
  sales_product_id: string
  child_sales_product_id: string | null
  material_item_id: string | null
  material_name: string
  hs_code: string | null
  bom_line_type: 'material' | 'service'
  quantity: number | null
  unit: string | null
  unit_price: number | null
  currency: string | null
  notes: string | null
  child_sales_product?: SalesProduct | null
  effective_material_name?: string | null
  effective_hs_code?: string | null
  effective_material_group?: { id: string; name: string; required_doc_types: string[] } | null
  linkage_status?: 'linked' | 'unlinked'
  primary_supplier?: { id: string; name: string; code: string | null; tier: number | null } | null
}

export interface EmissionReport {
  id: string
  sales_product_id: string
  supplier_id: string
  supplier?: { id: string; name: string }
  emissions_value: number
  calculation_note: string | null
  reported_at: string | null
  confirmed_at: string | null
}

export interface PcfSnapshot {
  id: string
  sales_product_id: string
  total_pcf: number | null
  functional_unit: string
  iso14067_ready: boolean
  snapshot_at: string
  lines: any[]
  pcr_ratio: number | null
  pcr_incomplete_lines: number
}

export interface ProductionBatch {
  id: string
  erp_batch_no: string | null
  erp_order_no: string | null
  production_date: string | null
  quantity: number | string | null
  unit: string | null
  lot_pcf: number | string | null
  lot_pcf_source: string | null
  supplier_name: string | null
  supplier_code: string | null
  source: string | null
}

export const salesProductApi = {
  list: (params?: Record<string, unknown>) =>
    http.get<{ success: boolean; data: SalesProduct[] }>('/api/v1/sales-products', { params }),

  search: (q: string) =>
    http.get<{ success: boolean; data: SalesProduct[] }>('/api/v1/sales-products/search', { params: { q } }),

  show: (id: string) =>
    http.get<{ success: boolean; data: SalesProduct & { upstream_details: SalesProductSupplier[]; production_batches: ProductionBatch[] } }>(`/api/v1/sales-products/${id}`),

  create: (payload: Partial<SalesProduct>) =>
    http.post<{ success: boolean; data: SalesProduct; message: string }>('/api/v1/sales-products', payload),

  update: (id: string, payload: Partial<SalesProduct>) =>
    http.put<{ success: boolean; data: SalesProduct; message: string }>(`/api/v1/sales-products/${id}`, payload),

  destroy: (id: string) =>
    http.delete<{ success: boolean; message: string }>(`/api/v1/sales-products/${id}`),

  cbamSummary: () =>
    http.get<{ success: boolean; data: any[] }>('/api/v1/sales-products/cbam-summary'),

  // 上游供應商
  suppliers: (id: string) =>
    http.get<{ success: boolean; data: SalesProductSupplier[] }>(`/api/v1/sales-products/${id}/suppliers`),

  addSupplier: (id: string, payload: { supplier_id: string; material_group_id?: string; supplier_facility_id?: string; notes?: string }) =>
    http.post<{ success: boolean; data: any; message: string }>(`/api/v1/sales-products/${id}/suppliers`, payload),

  removeSupplier: (productId: string, supplierId: string) =>
    http.delete<{ success: boolean; message: string }>(`/api/v1/sales-products/${productId}/suppliers/${supplierId}`),

  // 包材資訊
  packaging: (id: string) =>
    http.get<{ success: boolean; data: ProductPackaging | null }>(`/api/v1/sales-products/${id}/packaging`),

  upsertPackaging: (id: string, payload: Partial<ProductPackaging>) =>
    http.put<{ success: boolean; data: ProductPackaging; message: string }>(`/api/v1/sales-products/${id}/packaging`, payload),

  // 電池規格（dpp_category = battery 的產品專用）
  batterySpec: (id: string) =>
    http.get<{ success: boolean; data: ProductBatterySpec | null }>(`/api/v1/sales-products/${id}/battery-spec`),

  upsertBatterySpec: (id: string, payload: Partial<ProductBatterySpec>) =>
    http.put<{ success: boolean; data: ProductBatterySpec; message: string }>(`/api/v1/sales-products/${id}/battery-spec`, payload),

  // BOM
  bomLines: (id: string) =>
    http.get<{ success: boolean; data: BomLine[] }>(`/api/v1/sales-products/${id}/bom-lines`),

  createBomLine: (id: string, payload: Partial<BomLine>) =>
    http.post<{ success: boolean; data: BomLine; message: string }>(`/api/v1/sales-products/${id}/bom-lines`, payload),

  updateBomLine: (productId: string, lineId: string, payload: Partial<BomLine>) =>
    http.patch<{ success: boolean; data: BomLine; message: string }>(`/api/v1/sales-products/${productId}/bom-lines/${lineId}`, payload),

  destroyBomLine: (productId: string, lineId: string) =>
    http.delete<{ success: boolean; message: string }>(`/api/v1/sales-products/${productId}/bom-lines/${lineId}`),

  // 法規推算
  syncRegulations: (id: string) =>
    http.post<{ success: boolean; data: { id: string; inferred_regulations: string[]; applicable_regulations: string[] }; message: string }>(`/api/v1/sales-products/${id}/sync-regulations`),

  // DPP 就緒度
  dppReadiness: () =>
    http.get<{ success: boolean; data: any[] }>('/api/v1/compliance/dpp-readiness'),

  dppReadinessDetail: (id: string) =>
    http.get<{ success: boolean; data: any }>(`/api/v1/compliance/dpp-readiness/${id}`),

  // PCF
  pcfLatest: (id: string) =>
    http.get<{ data: PcfSnapshot | null }>(`/api/v1/sales-products/${id}/pcf-latest`),

  pcfRecalc: (id: string) =>
    http.post<{ data: PcfSnapshot }>(`/api/v1/sales-products/${id}/pcf-recalc`),

  // 碳排回報
  emissionReports: (id: string) =>
    http.get<{ success: boolean; data: EmissionReport[] }>(`/api/v1/sales-products/${id}/emission-reports`),

  confirmEmission: (productId: string, emissionId: string) =>
    http.post<{ success: boolean; message: string }>(
      `/api/v1/sales-products/${productId}/emission-reports/${emissionId}/confirm`
    ),

  import: (file: File) => {
    const form = new FormData()
    form.append('file', file)
    return http.post<{ success: boolean; created_count: number; skipped_count: number; warnings: string[] }>(
      '/api/v1/sales-products/import', form
    )
  },
}
