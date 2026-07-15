import http from '@/api/http'

export interface Scope3Category {
  category_number: number
  category_name: string
  co2e: number
}

export interface Scope3Report {
  reporting_year: number
  total_co2e: number
  categories: Scope3Category[]
}

export const reportsApi = {
  scope3: (year: number) =>
    http.get<{ success: boolean; data: Scope3Report }>('/api/v1/reports/scope3', { params: { year } }),

  exportScope3: async (year: number, format: 'xlsx' | 'pdf' = 'xlsx') => {
    const response = await http.get('/api/v1/reports/scope3/export', {
      params: { year, format },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([response.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = `scope3_${year}.${format === 'xlsx' ? 'csv' : 'html'}`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  },
}
