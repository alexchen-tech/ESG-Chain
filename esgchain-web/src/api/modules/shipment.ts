import http from '@/api/http'

export interface ShipmentLineBatch {
  id: string
  production_batch_id: string
  erp_batch_no: string | null
  supplier_name: string | null
  lot_pcf: string | null
  allocated_quantity: string
  origins_count: number
}

export interface ShipmentLine {
  id: string
  shipment_id: string
  trade_good_id: string
  trade_good_name: string | null
  trade_good_code: string | null
  hs_code: string | null
  is_eudr: boolean
  total_quantity: string
  unit: string
  weighted_pcf: string | null
  line_batches: ShipmentLineBatch[]
}

export interface Shipment {
  id: string
  shipment_no: string
  target_market: string
  export_date: string | null
  eudr_dds_status: 'not_required' | 'draft' | 'submitted' | 'approved'
  eudr_dds_ref: string | null
  eudr_submitted_at: string | null
  line_count: number
  notes: string | null
  creator: { id: string; name: string } | null
  lines?: ShipmentLine[]
  created_at: string
}

export interface DdsDraft {
  shipment_no: string
  target_market: string
  export_date: string | null
  eudr_dds_status: string
  commodities: {
    trade_good_code: string | null
    trade_good_name: string | null
    hs_code: string | null
    total_quantity: number
    unit: string
    weighted_pcf: number | null
    production_batches: {
      batch_no: string
      supplier: string | null
      allocated_quantity: number
      lot_pcf: number | null
      raw_material_origins: {
        material: string
        country: string
        facility: string | null
        gps: string | null
        harvest_year: number | null
        certification: string | null
      }[]
      origins_missing: boolean
    }[]
  }[]
  eudr_risk_assessment: string
  generated_at: string
}

export const shipmentApi = {
  list: () =>
    http.get<{ success: boolean; data: Shipment[] }>('/api/v1/shipments'),

  get: (id: string) =>
    http.get<{ success: boolean; data: Shipment }>(`/api/v1/shipments/${id}`),

  create: (payload: { shipment_no?: string; target_market: string; export_date?: string; eudr_dds_status?: string; notes?: string }) =>
    http.post<{ success: boolean; data: Shipment }>('/api/v1/shipments', payload),

  update: (id: string, payload: Partial<{ target_market: string; export_date: string; eudr_dds_status: string; eudr_dds_ref: string; notes: string }>) =>
    http.put<{ success: boolean; data: Shipment }>(`/api/v1/shipments/${id}`, payload),

  destroy: (id: string) =>
    http.delete<{ success: boolean }>(`/api/v1/shipments/${id}`),
}

export const shipmentLineApi = {
  create: (shipmentId: string, payload: { sales_product_id: string; total_quantity: number; unit?: string; hs_code_override?: string }) =>
    http.post<{ success: boolean; data: ShipmentLine }>(`/api/v1/shipments/${shipmentId}/lines`, payload),

  destroy: (shipmentId: string, lineId: string) =>
    http.delete<{ success: boolean }>(`/api/v1/shipments/${shipmentId}/lines/${lineId}`),
}

export const shipmentLineBatchApi = {
  create: (shipmentId: string, lineId: string, payload: { production_batch_id: string; allocated_quantity: number }) =>
    http.post<{ success: boolean; data: ShipmentLineBatch; warnings: string[] }>(`/api/v1/shipments/${shipmentId}/lines/${lineId}/batches`, payload),

  destroy: (shipmentId: string, lineId: string, batchId: string) =>
    http.delete<{ success: boolean }>(`/api/v1/shipments/${shipmentId}/lines/${lineId}/batches/${batchId}`),
}

export const ddsDraftApi = {
  get: (shipmentId: string) =>
    http.get<{ success: boolean; data: DdsDraft }>(`/api/v1/shipments/${shipmentId}/dds-draft`),
}
