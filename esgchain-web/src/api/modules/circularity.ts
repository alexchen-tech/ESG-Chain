import http from '@/api/http'

export interface CompositionBreakdownItem {
  fiber_type: string
  weight_kg: number
  percentage: number
}

export interface CircularitySnapshot {
  id: string
  sales_product_id: string
  total_weight_kg: number | null
  recycled_content_ratio: number | null
  pcr_ratio: number | null
  pir_ratio: number | null
  bio_based_ratio: number | null
  composition_breakdown: CompositionBreakdownItem[]
  recyclability_summary: Record<string, number>
  incomplete_lines_count: number
  data_ready: boolean
  calculated_at: string
}

export const circularityApi = {
  latest: (productId: string) =>
    http.get<{ success: boolean; data: CircularitySnapshot | null }>(`/api/v1/sales-products/${productId}/circularity-latest`),

  recalc: (productId: string) =>
    http.post<{ success: boolean; data: CircularitySnapshot }>(`/api/v1/sales-products/${productId}/circularity-recalc`),
}
