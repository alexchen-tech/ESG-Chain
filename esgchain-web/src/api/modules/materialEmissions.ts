import http from '@/api/http'

export interface MaterialItemEmission {
  id: string
  material_item_id: string
  supplier_id: string | null
  supplier_name?: string
  emissions_value: number
  source: 'portal-self' | 'buyer-input' | 'ai-estimated'
  calculation_method: string | null
  reported_period: string | null
  is_estimated: boolean
  is_flagged: boolean
  flag_reason: string | null
  reported_at: string
}

export interface MaterialEmissionGroup {
  supplier_id: string | null
  supplier_name: string
  latest: MaterialItemEmission
  history_count: number
  history: MaterialItemEmission[]
}

export interface PcfSnapshotLine {
  bom_line_id: string
  material_item_id: string | null
  material_name: string
  hs_code: string | null
  supplier_id: string | null
  supplier_name: string | null
  quantity: number
  unit: string
  emission_per_unit: number | null
  subtotal: number | null
  emission_source: 'portal-self' | 'buyer-input' | 'ai-estimated' | null
  is_estimated: boolean
  is_flagged: boolean
  reported_period: string | null
  data_quality: 'primary' | 'secondary' | 'estimated' | 'missing'
}

export interface PcfSnapshot {
  id: string
  sales_product_id: string
  total_pcf: number | null
  functional_unit: string
  iso14067_ready: boolean
  snapshot_at: string
  lines: PcfSnapshotLine[]
  pcr_ratio: number | null
  pcr_incomplete_lines: number | null
}

export const materialEmissionApi = {
  list(materialItemId: string) {
    return http.get<{ data: MaterialEmissionGroup[] }>(
      `/api/v1/material-items/${materialItemId}/emissions`
    )
  },

  create(materialItemId: string, payload: {
    supplier_id?: string
    emissions_value: number
    calculation_method?: string
    reported_period?: string
  }) {
    return http.post<{ data: MaterialItemEmission }>(
      `/api/v1/material-items/${materialItemId}/emissions`,
      payload
    )
  },

  flag(emissionId: string, reason: string) {
    return http.post<{ data: MaterialItemEmission }>(
      `/api/v1/material-emissions/${emissionId}/flag`,
      { reason }
    )
  },

  unflag(emissionId: string) {
    return http.post<{ data: MaterialItemEmission }>(
      `/api/v1/material-emissions/${emissionId}/unflag`
    )
  },
}

export const pcfSnapshotApi = {
  latest(productId: string) {
    return http.get<{ data: PcfSnapshot | null }>(
      `/api/v1/products/${productId}/pcf-latest`
    )
  },

  get(snapshotId: string) {
    return http.get<{ data: PcfSnapshot }>(
      `/api/v1/pcf-snapshots/${snapshotId}`
    )
  },

  recalc(productId: string) {
    return http.post<{ data: PcfSnapshot }>(
      `/api/v1/products/${productId}/pcf-recalc`
    )
  },
}

export const portalMaterialEmissionApi = {
  list() {
    return http.get<{ data: PortalMaterialEmissionItem[] }>('/api/v1/portal/material-emissions')
  },

  create(payload: {
    material_item_id: string
    emissions_value: number
    reported_period?: string
    calculation_method?: string
    calculation_note?: string
  }) {
    return http.post<{ data: MaterialItemEmission }>('/api/v1/portal/material-emissions', payload)
  },

  searchMaterials(keyword: string) {
    return http.get<{ data: MaterialItemSearchResult[] }>(
      `/api/v1/portal/material-items?keyword=${encodeURIComponent(keyword)}`
    )
  },
}

export interface PortalMaterialEmissionItem {
  material_item_id: string
  item_code: string
  name: string
  hs_code: string | null
  unit: string
  in_bom: boolean
  status: 'reported' | 'pending'
  latest_emission: {
    id: string
    emissions_value: number
    source: string
    is_estimated: boolean
    reported_period: string | null
    reported_at: string
  } | null
}

export interface MaterialItemSearchResult {
  id: string
  item_code: string
  name: string
  hs_code: string | null
  unit: string
}

export interface PcfRequestLineSummary {
  id: string
  material_name: string
  hs_code: string | null
  due_date: string | null
  status: 'pending' | 'submitted' | 'verified'
}

export const portalPcfRequestApi = {
  pendingLines() {
    return http.get<{ data: PcfRequestLineSummary[] }>('/api/v1/portal/pcf-request-lines?status=pending')
  },
}
