import http from '@/api/http'

export interface MaterialGroup {
  id: string
  name: string
  description: string | null
  hs_code_prefixes: string[]
  required_doc_types: string[]
  is_system: boolean
}


export interface SupplierComplianceDoc {
  id: string
  supplier_id: string
  trade_good_id: string | null
  doc_type: string
  file_path: string
  file_name: string | null
  issued_at: string | null
  expires_at: string | null
  status: 'valid' | 'expiring_soon' | 'expired' | 'pending'
  verified_at: string | null
  verified_by: string | null
  notes: string | null
  created_at: string
}

export interface SupplierComplianceSummary {
  supplier_id: string
  supplier_name?: string
  total_docs: number
  valid_count: number
  expiring_soon_count: number
  expired_count: number
  pending_count: number
  missing_required_types: string[]
}

export interface ProductComplianceStatus {
  product_id: string
  product_name: string
  applicable_regulations: string[]
  overall_status: 'valid' | 'expiring_soon' | 'expired' | 'pending' | 'unconfigured'
  supplier_results: {
    supplier_id: string
    supplier_name: string
    material_group_id: string | null
    material_group: string | null
    required_doc_types: string[]
    missing_doc_types: string[]
    compliance_summary: Record<string, number> & { status: string }
  }[]
}

export const materialGroupApi = {
  list: () => http.get<{ success: boolean; data: MaterialGroup[] }>('/api/v1/material-groups'),
  create: (data: Partial<MaterialGroup>) => http.post('/api/v1/material-groups', data),
  update: (id: string, data: Partial<MaterialGroup>) => http.put(`/api/v1/material-groups/${id}`, data),
  destroy: (id: string) => http.delete<{ success: boolean; message: string }>(`/api/v1/material-groups/${id}`),
}


export const complianceDocApi = {
  list: (supplierId: string, params?: { status?: string }) =>
    http.get<{ success: boolean; data: SupplierComplianceDoc[] }>(`/api/v1/suppliers/${supplierId}/compliance-docs`, { params }),
  create: (supplierId: string, formData: FormData) =>
    http.post(`/api/v1/suppliers/${supplierId}/compliance-docs`, formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
  destroy: (docId: string) => http.delete(`/api/v1/compliance-docs/${docId}`),
  verify: (docId: string, body?: { expires_at?: string; notes?: string }) =>
    http.post(`/api/v1/compliance-docs/${docId}/verify`, body ?? {}),
  unverify: (docId: string) => http.delete(`/api/v1/compliance-docs/${docId}/verify`),
  download: (docId: string) =>
    http.get(`/api/v1/compliance-docs/${docId}/download`, { responseType: 'blob' }),
  getSupplierSummary: (supplierId: string) =>
    http.get<{ success: boolean; data: SupplierComplianceSummary }>(`/api/v1/suppliers/${supplierId}/compliance-docs/summary`),
}

export interface TradeGood {
  id: string
  supplier_id: string
  name: string
  hs_code: string | null
  unit: string | null
  annual_value_usd: number | null
  material_group_id: string | null
  material_group?: { id: string; name: string; required_doc_types: string[] }
}

export const supplierTradeGoodsApi = {
  listBySupplier: (supplierId: string) =>
    http.get<{ success: boolean; data: TradeGood[] }>('/api/v1/trade-goods', { params: { supplier_id: supplierId } }),
}

export interface BomLineSupplierRecord {
  id: string
  bom_line_id: string
  supplier_id: string
  role: 'primary' | 'alternate'
  source: 'erp_designated' | 'manual'
  sort_order: number
  supplier?: { id: string; name: string; code: string }
}

export interface MaterialItem {
  id: string
  item_code: string
  name: string
  hs_code: string | null
  unit: string | null
  material_group_id: string | null
  material_group?: { id: string; name: string; required_doc_types: string[] }
  description: string | null
  is_active: boolean
  net_weight: number | null
  pcr_percentage: number | null
  pir_percentage: number | null
  bio_based_percentage: number | null
  recyclability_rating: 'high' | 'medium' | 'low' | 'not_rated' | null
  microfiber_release_risk: 'low' | 'medium' | 'high' | 'not_rated' | null
  fiber_type: string | null
  created_at: string
}

export interface MaterialItemSupplier {
  id: string
  material_item_id: string
  supplier_id: string
  role: 'primary' | 'alternate'
  source: 'erp_designated' | 'manual'
  sort_order: number
  supplier?: { id: string; name: string; code: string | null; onboarding_stage: string }
}

export interface MaterialBomLineSupplierOption {
  bom_line_supplier_id: string
  supplier_id: string
  supplier_name: string
  supplier_code: string | null
  role: 'primary' | 'alternate'
  emissions_value: number | null
  source: string | null
  is_flagged: boolean
  reported_period: string | null
}

export interface MaterialBomLineRow {
  bom_line_id: string
  sales_product_id: string
  product_name: string | null
  product_code: string | null
  product_model_no: string | null
  current_primary_supplier_id: string | null
  suppliers: MaterialBomLineSupplierOption[]
}

export interface ProductBomLine {
  id: string
  sales_product_id: string
  material_item_id: string | null
  material_item?: MaterialItem | null
  erp_line_id: string | null
  material_name: string
  hs_code: string | null
  material_group_id: string | null
  material_group_source: 'erp_imported' | 'hs_inferred' | 'manual' | null
  bom_line_type: 'material' | 'service'
  notes: string | null
  material_group?: { id: string; name: string; required_doc_types: string[] }
  bom_line_suppliers?: BomLineSupplierRecord[]
  // effective 欄位（後端計算，有料號時以料號資料為準）
  effective_material_name?: string
  effective_hs_code?: string | null
  effective_material_group?: { id: string; name: string; required_doc_types: string[] } | null
  created_at: string
  updated_at: string
}

export interface BomRequirementLine {
  bom_line_id: string
  material_name: string
  hs_code: string | null
  material_group: string | null
  required_doc_types: string[]
  submitted_docs: { doc_type: string; status: string; expires_at: string | null }[]
  pcf_status: 'none' | 'pending' | 'submitted' | 'verified'
}

export interface BomRequirementProduct {
  product_index: number
  product_id: string
  product_name: string
  applicable_regulations: string[]
  bom_lines: BomRequirementLine[]
}

export const bomLineApi = {
  list: (productId: string) =>
    http.get<{ success: boolean; data: ProductBomLine[] }>(`/api/v1/sales-products/${productId}/bom-lines`),
  create: (productId: string, data: Partial<ProductBomLine>) =>
    http.post<{ success: boolean; data: ProductBomLine }>(`/api/v1/sales-products/${productId}/bom-lines`, data),
  update: (productId: string, lineId: string, data: Partial<ProductBomLine>) =>
    http.patch<{ success: boolean; data: ProductBomLine }>(`/api/v1/sales-products/${productId}/bom-lines/${lineId}`, data),
  destroy: (productId: string, lineId: string) =>
    http.delete(`/api/v1/sales-products/${productId}/bom-lines/${lineId}`),
  import: (productId: string, formData: FormData) =>
    http.post<{ success: boolean; data: { created: number; updated: number; warnings: string[] } }>(
      `/api/v1/sales-products/${productId}/bom-lines/import`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    ),
}

export const materialItemApi = {
  list: (params?: { search?: string; active_only?: boolean; per_page?: number; page?: number; material_group_ids?: string }) =>
    http.get<{ success: boolean; data: { data: MaterialItem[]; total: number; per_page: number; current_page: number } }>('/api/v1/material-items', { params }),
  show: (id: string) =>
    http.get<{ success: boolean; data: MaterialItem }>(`/api/v1/material-items/${id}`),
  listAll: () =>
    http.get<{ success: boolean; data: { data: MaterialItem[] } }>('/api/v1/material-items', { params: { active_only: true, per_page: 1000 } }),
  create: (data: Partial<MaterialItem>) =>
    http.post<{ success: boolean; data: MaterialItem }>('/api/v1/material-items', data),
  update: (id: string, data: Partial<MaterialItem>) =>
    http.put<{ success: boolean; data: MaterialItem }>(`/api/v1/material-items/${id}`, data),
  destroy: (id: string) =>
    http.delete<{ success: boolean; message: string; bom_line_count?: number }>(`/api/v1/material-items/${id}`),
  import: (file: File) => {
    const form = new FormData()
    form.append('file', file)
    return http.post<{ success: boolean; data: { created: number; updated: number; warnings: string[] } }>(
      '/api/v1/material-items/import',
      form,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    )
  },
  bomSuppliers: (id: string) =>
    http.get<{ success: boolean; data: MaterialBomLineRow[] }>(`/api/v1/material-items/${id}/bom-suppliers`),
  switchPrimarySupplier: (materialItemId: string, bomLineId: string, supplierId: string) =>
    http.post<{ success: boolean; message: string }>(
      `/api/v1/material-items/${materialItemId}/bom-lines/${bomLineId}/switch-primary-supplier`,
      { supplier_id: supplierId }
    ),

  // 物料層級核可供應商清單（主/備），所有使用此物料的產品共用，取代逐產品重複登記
  approvedSuppliers: (id: string) =>
    http.get<{ success: boolean; data: MaterialItemSupplier[] }>(`/api/v1/material-items/${id}/suppliers`),
  addApprovedSupplier: (id: string, payload: { supplier_id: string; role: 'primary' | 'alternate' }) =>
    http.post<{ success: boolean; data: MaterialItemSupplier; message?: string }>(`/api/v1/material-items/${id}/suppliers`, payload),
  setApprovedSupplierRole: (id: string, approvedSupplierId: string, role: 'primary' | 'alternate') =>
    http.patch<{ success: boolean; data: MaterialItemSupplier }>(`/api/v1/material-items/${id}/suppliers/${approvedSupplierId}/role`, { role }),
  removeApprovedSupplier: (id: string, approvedSupplierId: string) =>
    http.delete<{ success: boolean; message: string }>(`/api/v1/material-items/${id}/suppliers/${approvedSupplierId}`),
  applyApprovedSuppliersToBomLine: (id: string, bomLineId: string) =>
    http.post<{ success: boolean; message: string; data: any }>(`/api/v1/material-items/${id}/suppliers/apply-to-bom-line`, { bom_line_id: bomLineId }),
}

export const bomLineSupplierApi = {
  store: (productId: string, bomLineId: string, data: { supplier_id: string; role: 'primary' | 'alternate' }) =>
    http.post<{ success: boolean; data: BomLineSupplierRecord }>(`/api/v1/sales-products/${productId}/bom-lines/${bomLineId}/suppliers`, data),
  destroy: (productId: string, bomLineId: string, bomLineSupplierId: string) =>
    http.delete(`/api/v1/sales-products/${productId}/bom-lines/${bomLineId}/suppliers/${bomLineSupplierId}`),
  setRole: (productId: string, bomLineId: string, bomLineSupplierId: string, role: 'primary' | 'alternate') =>
    http.patch<{ success: boolean; data: BomLineSupplierRecord }>(`/api/v1/sales-products/${productId}/bom-lines/${bomLineId}/suppliers/${bomLineSupplierId}/role`, { role }),
}

export const supplierBomRequirementsApi = {
  get: (supplierId: string) =>
    http.get<{ success: boolean; data: BomRequirementProduct[] }>(`/api/v1/suppliers/${supplierId}/bom-requirements`),
}

export const portalProcurementApi = {
  getRequirements: () =>
    http.get<{ success: boolean; data: BomRequirementProduct[] }>('/api/v1/portal/procurement-requirements'),
}

export interface MatrixCell {
  total: number
  compliant: number
  expiring: number
  issues: number
  pct: number
}

export interface MatrixRow {
  material_group_id: string
  material_group_name: string
  cells: Record<string, MatrixCell | null>
}

export interface MatrixData {
  doc_types: string[]
  rows: MatrixRow[]
}

export interface MatrixDrillSupplier {
  supplier_id: string
  supplier_name: string
  supplier_group: string | null
  status: 'valid' | 'expiring_soon' | 'expired' | 'missing'
  expires_at: string | null
  tier: number
  risk_score: number | null
  onboarding_stage: string
}

export interface MatrixDrillData {
  material_group_name: string
  doc_type: string
  suppliers: MatrixDrillSupplier[]
}

export interface DppProduct {
  product_id: string
  product_name: string
  has_espr_regulation: boolean
  readiness_status: 'ready' | 'partial' | 'not_started'
  material_completeness_pct: number
  supplier_compliance_pct: number
  bom_line_count: number
  issues: string[]
  inferred_regulations: string[]
  applicable_regulations: string[]
}

export interface DppMaterialItem {
  material_name: string
  hs_code: string | null
  material_group: string | null
  has_group: boolean
}

export interface DppSupplierItem {
  supplier_name: string
  doc_type: string
  status: 'valid' | 'expiring_soon' | 'expired' | 'missing'
  expires_at: string | null
}

export interface DppDetail extends DppProduct {
  sections: {
    material_list: {
      status: string
      total: number
      complete: number
      items: DppMaterialItem[]
    }
    supplier_compliance: {
      status: string
      total_required: number
      compliant: number
      items: DppSupplierItem[]
    }
    regulations: {
      has_espr: boolean
      all_regulations: string[]
      inferred_regulations: string[]
      applicable_regulations: string[]
    }
  }
}

export interface PendingVerificationDoc {
  id: string
  supplier_id: string
  supplier_name: string | null
  doc_type: string
  file_name: string | null
  uploaded_at: string | null
  expires_at: string | null
  missing_expiry: boolean
  status: 'valid' | 'expiring_soon' | 'expired' | 'pending'
}

export const complianceDashboardApi = {
  getSupplierDashboard: () =>
    http.get<{ success: boolean; data: SupplierComplianceSummary[] }>('/api/v1/compliance/dashboard'),
  getProductDashboard: () =>
    http.get<{ success: boolean; data: ProductComplianceStatus[] }>('/api/v1/compliance/product-dashboard'),
  matrix: (params?: { supplier_group_id?: string; tier?: number | string; risk_score_min?: number | string }) =>
    http.get<{ success: boolean; data: MatrixData }>('/api/v1/compliance/matrix', { params }),
  matrixDrill: (params: { material_group_id: string; doc_type: string; supplier_group_id?: string; tier?: number | string; risk_score_min?: number | string }) =>
    http.get<{ success: boolean; data: MatrixDrillData }>('/api/v1/compliance/matrix/drill', { params }),
  dppReadiness: () =>
    http.get<{ success: boolean; data: DppProduct[] }>('/api/v1/compliance/dpp-readiness'),
  dppReadinessDetail: (productId: string) =>
    http.get<{ success: boolean; data: DppDetail }>(`/api/v1/compliance/dpp-readiness/${productId}`),
  syncProductRegulations: (productId: string) =>
    http.post<{ success: boolean; data: { id: string; inferred_regulations: string[]; applicable_regulations: string[] }; message: string }>(`/api/v1/compliance/products/${productId}/sync-regulations`),
  pendingVerifications: () =>
    http.get<{ success: boolean; data: PendingVerificationDoc[] }>('/api/v1/compliance/pending-verifications'),
}

export const tradeGoodSearchApi = {
  search: (keyword: string) =>
    http.get<{ success: boolean; data: { id: string; name: string; product_code: string | null; hs_code: string | null }[] }>(`/api/v1/trade-goods/search?q=${encodeURIComponent(keyword)}`),
}
