import http from '@/api/http'

export interface CarbonPriceSetting {
  carbon_price_eur: number
  is_default: boolean
  updated_at: string | null
  updated_by: string | null
}

export const carbonPriceApi = {
  get: () =>
    http.get<{ success: boolean; data: CarbonPriceSetting }>('/api/v1/settings/carbon-price'),

  update: (carbonPriceEur: number) =>
    http.put<{ success: boolean; data: { carbon_price_eur: number }; message: string }>(
      '/api/v1/settings/carbon-price',
      { carbon_price_eur: carbonPriceEur }
    ),
}
