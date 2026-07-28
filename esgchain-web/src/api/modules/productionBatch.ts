import http from '@/api/http'
import type { ExportMarket } from '@/constants/markets'

export interface RawMaterialOrigin {
  id: string
  production_batch_id: string
  bom_line_id: string | null
  supplier_id: string | null
  material_name: string
  origin_country: string
  facility_name: string | null
  gps_lat: string | null
  gps_lng: string | null
  harvest_year: number | null
  certification_ref: string | null
  transport_mode: 'sea' | 'air' | 'road' | 'rail' | 'multimodal' | 'unknown' | null
  transport_distance_km: number | null
}

export interface ProductionBatch {
  id: string
  erp_batch_no: string
  erp_order_no: string | null
  matched: boolean
  sales_product_id: string | null
  sales_product_name: string | null
  sales_product_code: string | null
  sales_product_model_no: string | null
  supplier_id: string
  supplier_name: string | null
  supplier_code: string | null
  production_date: string | null
  quantity: string
  unit: string
  lot_pcf: string | null
  lot_pcf_source: 'calculated' | 'reported' | 'estimated' | null
  source: 'webhook' | 'csv' | 'manual'
  erp_synced_at: string | null
  raw_material_origins: RawMaterialOrigin[]
  created_at: string
}

export interface BatchExportReviewFinding {
  check: string
  label: string
  status: string
  detail: string
  // 新格式（逐份文件 finding）才有以下欄位；舊資料（重跑審查前）沒有，前端需相容顯示
  doc_type?: string
  doc_status?: 'missing' | 'expiring_soon' | 'expired'
  expires_at?: string | null
  supplier_id?: string | null
  supplier_name?: string | null
}

export interface BatchExportReview {
  id: string
  production_batch_id: string
  market: ExportMarket
  // null 代表完整審查（涵蓋全部法規範疇）；非 null 代表僅審查該範疇
  program?: string | null
  status: 'pending' | 'pass' | 'warning' | 'fail'
  findings: BatchExportReviewFinding[] | null
  reviewed_at: string | null
  // 審查後若相關供應商合規文件有更新，視為可能已過期，提示使用者重新確認（不自動失效）
  possibly_stale?: boolean
}

export interface DdsDraft {
  erp_batch_no: string
  market: string
  supplier: string | null
  trade_good_code: string | null
  trade_good_name: string | null
  hs_code: string | null
  quantity: number
  unit: string
  lot_pcf: number | null
  lot_pcf_source: string | null
  raw_material_origins: {
    material: string
    country: string
    facility: string | null
    gps: string | null
    harvest_year: number | null
    certification: string | null
  }[]
  origins_missing: boolean
  export_review: { market: string; program?: string | null; status: string; blocked: boolean; reviewed_at: string | null; possibly_stale?: boolean; findings: any[] }
  generated_at: string
}

export interface BatchPassport {
  schema_version: string
  generated_at: string
  batch: {
    erp_batch_no: string
    erp_order_no: string | null
    production_date: string | null
    quantity: number
    unit: string
    supplier: { name: string; code: string | null } | null
  }
  product: {
    name: string
    product_code: string | null
    model_no: string | null
    hs_code: string | null
    applicable_regulations: string[]
    inferred_regulations: string[]
  } | null
  carbon_footprint: {
    batch_lot_pcf: number | null
    batch_lot_pcf_source: string | null
    product_total_pcf: number | null
    functional_unit: string | null
    iso14067_ready: boolean
    iso14067_gap_reasons: string[]
    snapshot_at: string | null
  }
  circularity: {
    recycled_content_ratio: number | null
    pcr_ratio: number | null
    pir_ratio: number | null
    bio_based_ratio: number | null
    composition_breakdown: { fiber_type: string; weight_kg: number; percentage: number }[]
    recyclability_summary: Record<string, number>
    data_ready: boolean
    calculated_at: string | null
  } | null
  packaging: {
    recycled_content_ratio: number | null
    recyclable: boolean | null
    reusable: boolean | null
    material_description: string | null
  } | null
  battery_spec: {
    battery_category: string | null
    chemistry: string | null
    rated_capacity_ah: number | null
    rated_voltage_v: number | null
    weight_kg: number | null
    critical_materials: {
      lithium_recycled_content_ratio: number | null
      cobalt_recycled_content_ratio: number | null
      nickel_recycled_content_ratio: number | null
      lead_recycled_content_ratio: number | null
    }
    performance: {
      cycle_life: number | null
      expected_lifetime_years: number | null
      discharge_efficiency_ratio: number | null
      initial_capacity_soh_note: string | null
      operating_temp_range: string | null
    }
  } | null
  hazard_disclosure: {
    has_hazardous_substance: boolean
    alerts: { chemical_id: string | null; chemical_name: string | null; alert_level: string; status: string }[]
  } | null
  microfiber_release_risks: { material_name: string; risk: string }[]
  compliance_documents: {
    doc_type: string
    status: string
    issued_at: string | null
    expires_at: string | null
    supplier_name: string | null
  }[]
  traceability: {
    origins: {
      material: string
      country: string
      facility: string | null
      gps: string | null
      harvest_year: number | null
      certification: string | null
      transport_mode?: string | null
      transport_distance_km?: number | null
    }[]
  }
  process_locations: { process_type: string; facility_name: string; country: string | null }[]
  process_due_diligence: ProcessDueDiligenceItem[]
  supply_chain_compliance: {
    bom_line_id: string
    material_name: string
    required_doc_types: string[]
    selected_supplier: { id: string; name: string; code: string | null } | null
    supplier_confirmed: boolean
    traceability: {
      origin_country: string | null
      facility_name: string | null
      gps: string | null
      harvest_year: number | null
      certification_ref: string | null
      transport_mode: string | null
      transport_distance_km: number | null
    } | null
    doc_statuses: { doc_type: string; status: string; expires_at: string | null }[]
  }[]
  export_market_reviews: {
    market: string
    status: string
    reviewed_at: string | null
    findings: BatchExportReviewFinding[]
  }[]
}

export interface ProcessFacilityCandidate {
  supplier_id: string
  supplier_name: string | null
  facility_id: string | null
  facility_name: string | null
  country: string | null
}

export interface BatchProcessFacilitySelected {
  id: string
  supplier_id: string
  supplier_name: string | null
  facility_id: string | null
  facility_name: string | null
  country: string | null
}

export interface BatchProcessFacilityItem {
  process_type: string
  confirmed: boolean
  selected: BatchProcessFacilitySelected | null
  candidates: ProcessFacilityCandidate[]
}

export interface ProcessDueDiligenceItem {
  process_type: string
  dimension: string
  dimension_label: string
  status: 'assessed' | 'not_assessed' | 'pending_selection'
  risk_level: 'low' | 'medium' | 'high' | 'unknown' | null
  score: number | null
  supplier_id: string | null
  supplier_name: string | null
}

export interface ExportReviewQueueItem {
  id: string
  erp_batch_no: string
  erp_order_no: string | null
  production_date: string | null
  quantity: string
  unit: string
  supplier_name: string | null
  supplier_code: string | null
  sales_product_id: string | null
  product_name: string | null
  product_code: string | null
  // 未篩選市場時僅為「最新建立」那一筆審查的市場，不代表該批次只審查過這一個市場
  market: string | null
  status: 'pending' | 'pass' | 'warning' | 'fail' | 'unreviewed'
  reviewed_at: string | null
  possibly_stale: boolean
}

export interface Pagination {
  total: number
  per_page: number
  current_page: number
  last_page: number
}

export const exportReviewQueueApi = {
  list: (filters?: Record<string, string | number>) =>
    http.get<{ success: boolean; data: ExportReviewQueueItem[]; pagination: Pagination }>(
      '/api/v1/export-reviews', { params: filters }),
}

export const productionBatchApi = {
  list: (filters?: Record<string, string>) =>
    http.get<{ success: boolean; data: ProductionBatch[] }>('/api/v1/production-batches', { params: filters }),

  // 批號×市場出口合規審查
  exportReviews: (batchId: string) =>
    http.get<{ success: boolean; data: BatchExportReview[] }>(`/api/v1/production-batches/${batchId}/export-reviews`),

  runExportReview: (batchId: string, market: string, program?: string | null) =>
    http.post<{ success: boolean; data: BatchExportReview; message: string }>(
      `/api/v1/production-batches/${batchId}/export-reviews`, { market, program: program || undefined }),

  deleteExportReview: (batchId: string, reviewId: string) =>
    http.delete<{ success: boolean }>(`/api/v1/production-batches/${batchId}/export-reviews/${reviewId}`),

  ddsDraft: (batchId: string, market: string) =>
    http.get<{ success: boolean; data: DdsDraft }>(`/api/v1/production-batches/${batchId}/dds-draft`, { params: { market } }),

  get: (id: string) =>
    http.get<{ success: boolean; data: ProductionBatch }>(`/api/v1/production-batches/${id}`),

  update: (id: string, payload: Partial<Pick<ProductionBatch, 'production_date' | 'quantity' | 'unit' | 'lot_pcf' | 'lot_pcf_source'>>) =>
    http.put<{ success: boolean; data: ProductionBatch }>(`/api/v1/production-batches/${id}`, payload),

  destroy: (id: string) =>
    http.delete<{ success: boolean }>(`/api/v1/production-batches/${id}`),

  passport: (id: string) =>
    http.get<{ success: boolean; data: BatchPassport }>(`/api/v1/production-batches/${id}/passport`),
}

export const processFacilityApi = {
  list: (batchId: string) =>
    http.get<{ success: boolean; data: BatchProcessFacilityItem[] }>(`/api/v1/production-batches/${batchId}/process-facilities`),

  select: (batchId: string, payload: { process_type: string; supplier_id: string; supplier_facility_id?: string | null }) =>
    http.post<{ success: boolean; data: any; message: string }>(`/api/v1/production-batches/${batchId}/process-facilities`, payload),

  clear: (batchId: string, id: string) =>
    http.delete<{ success: boolean }>(`/api/v1/production-batches/${batchId}/process-facilities/${id}`),
}

export const processDueDiligenceApi = {
  list: (batchId: string) =>
    http.get<{ success: boolean; data: ProcessDueDiligenceItem[] }>(`/api/v1/production-batches/${batchId}/process-due-diligence`),
}

export const rawMaterialOriginApi = {
  create: (batchId: string, payload: Omit<RawMaterialOrigin, 'id' | 'production_batch_id'>) =>
    http.post<{ success: boolean; data: RawMaterialOrigin }>(`/api/v1/production-batches/${batchId}/origins`, payload),

  update: (batchId: string, id: string, payload: Partial<Omit<RawMaterialOrigin, 'id' | 'production_batch_id'>>) =>
    http.put<{ success: boolean; data: RawMaterialOrigin }>(`/api/v1/production-batches/${batchId}/origins/${id}`, payload),

  destroy: (batchId: string, id: string) =>
    http.delete<{ success: boolean }>(`/api/v1/production-batches/${batchId}/origins/${id}`),
}
