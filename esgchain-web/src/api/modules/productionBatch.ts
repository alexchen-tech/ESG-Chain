import http from '@/api/http'

export interface RawMaterialOrigin {
  id: string
  production_batch_id: string
  bom_line_id: string | null
  material_name: string
  origin_country: string
  facility_name: string | null
  gps_lat: string | null
  gps_lng: string | null
  harvest_year: number | null
  certification_ref: string | null
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
  trade_good_name?: string | null
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

export interface BatchExportReview {
  id: string
  production_batch_id: string
  market: 'EU' | 'US' | 'UK' | 'JP' | 'GLOBAL'
  status: 'pending' | 'pass' | 'warning' | 'fail'
  findings: { check: string; label: string; status: string; detail: string }[] | null
  reviewed_at: string | null
}

export const productionBatchApi = {
  list: (filters?: Record<string, string>) =>
    http.get<{ success: boolean; data: ProductionBatch[] }>('/api/v1/production-batches', { params: filters }),

  // 批號×市場出口合規審查
  exportReviews: (batchId: string) =>
    http.get<{ success: boolean; data: BatchExportReview[] }>(`/api/v1/production-batches/${batchId}/export-reviews`),

  runExportReview: (batchId: string, market: string) =>
    http.post<{ success: boolean; data: BatchExportReview; message: string }>(
      `/api/v1/production-batches/${batchId}/export-reviews`, { market }),

  deleteExportReview: (batchId: string, reviewId: string) =>
    http.delete<{ success: boolean }>(`/api/v1/production-batches/${batchId}/export-reviews/${reviewId}`),

  get: (id: string) =>
    http.get<{ success: boolean; data: ProductionBatch }>(`/api/v1/production-batches/${id}`),

  update: (id: string, payload: Partial<Pick<ProductionBatch, 'production_date' | 'quantity' | 'unit' | 'lot_pcf' | 'lot_pcf_source'>>) =>
    http.put<{ success: boolean; data: ProductionBatch }>(`/api/v1/production-batches/${id}`, payload),

  destroy: (id: string) =>
    http.delete<{ success: boolean }>(`/api/v1/production-batches/${id}`),
}

export const rawMaterialOriginApi = {
  create: (batchId: string, payload: Omit<RawMaterialOrigin, 'id' | 'production_batch_id'>) =>
    http.post<{ success: boolean; data: RawMaterialOrigin }>(`/api/v1/production-batches/${batchId}/origins`, payload),

  update: (batchId: string, id: string, payload: Partial<Omit<RawMaterialOrigin, 'id' | 'production_batch_id'>>) =>
    http.put<{ success: boolean; data: RawMaterialOrigin }>(`/api/v1/production-batches/${batchId}/origins/${id}`, payload),

  destroy: (batchId: string, id: string) =>
    http.delete<{ success: boolean }>(`/api/v1/production-batches/${batchId}/origins/${id}`),
}
