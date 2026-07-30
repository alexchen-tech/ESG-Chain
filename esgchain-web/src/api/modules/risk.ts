import http from '@/api/http'

// ── 六維熱圖 ──────────────────────────────────────────────────────────
export interface SixDimHeatmapRow {
  supplier_id: string
  supplier_name: string
  supplier_code: string
  country_code: string
  group_id: string | null
  group_name: string | null
  assessed_at: string | null
  source_type: 'saq' | 'geo_event' | 'regulation_change' | 'manual_review'
  dim_e1: number | null
  dim_e2: number | null
  dim_e3: number | null
  dim_e4: number | null
  dim_e5: number | null
  dim_e6: number | null
  open_cap_count: number
  risk_score: number | null
  is_critical: boolean
}

export interface SixDimHeatmapResponse {
  data: SixDimHeatmapRow[]
  thresholds: Record<string, number>
  summary: { total: number; any_dim_critical: number }
}

// ── 地緣事件 ──────────────────────────────────────────────────────────
export interface GeoEvent {
  id: string
  name: string
  event_type: string
  affected_scope: { country_codes: string[] }
  severity: 'low' | 'medium' | 'high' | 'critical'
  status: 'active' | 'archived'
  occurred_at: string
  supplier_reviews_count?: number
  done_count?: number
}

export interface GeoEventSupplierReview {
  id: string
  geo_event_id: string
  supplier_id: string
  status: 'pending' | 'recalculating' | 'done' | 'failed'
  pre_e4_score: number | null
  post_e4_score: number | null
  risk_assessment_id: string | null
  supplier?: { id: string; name: string; code: string; country_code: string }
}

// ── 5×5 風險矩陣 ──────────────────────────────────────────────────────
export type RiskDimension = 'E1' | 'E2' | 'E3' | 'E4' | 'E5' | 'COMPOSITE'

export interface RiskMatrixCell {
  probability: number
  impact: number
  cell_score: number
  risk_level: 'very_low' | 'low' | 'medium' | 'high' | 'extreme'
  supplier_count: number
  open_cap_count: number
  saq_grade_dist: Record<string, number>
  no_saq_count: number
}

export interface RiskMatrixSummary {
  extreme_count: number
  high_count: number
  medium_count: number
  low_count: number
  very_low_count: number
  total: number
}

// ── Legacy ─────────────────────────────────────────────────────────────
export interface RiskSummary {
  supplier_id: string
  assessment_version: string
  source_type: string
  assessed_at: string | null
  six_dims: Record<string, number | null>
}

export const riskApi = {
  // 六維熱圖
  sixDimHeatmap: (params?: { before_days?: number }) =>
    http.get<SixDimHeatmapResponse>('/api/v1/risk/six-dim-heatmap', { params }),

  // 風險評估列表（供應商詳情頁使用）
  assessments: (params?: { supplier_id?: string; page?: number }) =>
    http.get('/api/v1/risk/assessments', { params }),

  riskSummary: (supplierId: string) =>
    http.get<{ success: boolean; data: RiskSummary }>(`/api/v1/suppliers/${supplierId}/risk-summary`),

  // 地緣事件 CRUD
  geoEvents: (params?: { page?: number; per_page?: number }) =>
    http.get<{ data: GeoEvent[]; current_page: number; per_page: number; last_page: number; total: number }>('/api/v1/risk/geo-events', { params }),

  createGeoEvent: (data: Partial<GeoEvent>) =>
    http.post<GeoEvent>('/api/v1/risk/geo-events', data),

  getGeoEvent: (id: string) =>
    http.get<GeoEvent>(`/api/v1/risk/geo-events/${id}`),

  geoEventReviews: (id: string, params?: { page?: number }) =>
    http.get<{ data: GeoEventSupplierReview[]; total: number }>(`/api/v1/risk/geo-events/${id}/reviews`, { params }),

  recalculateE4: (id: string) =>
    http.post(`/api/v1/risk/geo-events/${id}/recalculate`),

  matrix: (dim: RiskDimension) =>
    http.get<{ data: { matrix: RiskMatrixCell[]; summary: RiskMatrixSummary } }>('/api/v1/risk/matrix', { params: { dimension: dim } }),

  matrixSuppliers: (dim: RiskDimension, probability: number, impact: number) =>
    http.get('/api/v1/risk/matrix/suppliers', { params: { dimension: dim, probability, impact } }),
}
