import http from '@/api/http'

export interface NetworkNode {
  id: string
  type: 'supplier' | 'material' | 'product'
  label: string
  ref_id: string | null
  code?: string | null
  tier?: number | null
  country?: string | null
}

export interface NetworkEdge {
  source: string
  target: string
}

export const supplierNetworkApi = {
  graph: (salesProductId: string) =>
    http.get<{ success: boolean; data: { nodes: NetworkNode[]; edges: NetworkEdge[] } }>('/api/v1/suppliers/network-graph', {
      params: { sales_product_id: salesProductId },
    }),
}
