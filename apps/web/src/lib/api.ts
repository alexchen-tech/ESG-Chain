import axios from 'axios'
import { getSession } from 'next-auth/react'

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:3000'

export const apiClient = axios.create({
  baseURL: API_URL,
  headers: { 'Content-Type': 'application/json' },
})

apiClient.interceptors.request.use(async (config) => {
  const session = await getSession()
  if ((session as any)?.accessToken) {
    config.headers.Authorization = `Bearer ${(session as any).accessToken}`
  }
  return config
})

apiClient.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

// Suppliers
export const suppliersApi = {
  list: (params?: Record<string, unknown>) => apiClient.get('/suppliers', { params }),
  get: (id: string) => apiClient.get(`/suppliers/${id}`),
  create: (data: unknown) => apiClient.post('/suppliers', data),
  update: (id: string, data: unknown) => apiClient.patch(`/suppliers/${id}`, data),
  delete: (id: string) => apiClient.delete(`/suppliers/${id}`),
}

// SAQ
export const saqApi = {
  list: (params?: Record<string, unknown>) => apiClient.get('/saq', { params }),
  get: (id: string) => apiClient.get(`/saq/${id}`),
  create: (data: unknown) => apiClient.post('/saq', data),
  update: (id: string, data: unknown) => apiClient.patch(`/saq/${id}`, data),
  send: (id: string) => apiClient.post(`/saq/${id}/send`),
  submit: (id: string, responses: unknown) => apiClient.post(`/saq/${id}/submit`, { responses }),
}

// CAP
export const capApi = {
  list: (params?: Record<string, unknown>) => apiClient.get('/cap', { params }),
  get: (id: string) => apiClient.get(`/cap/${id}`),
  create: (data: unknown) => apiClient.post('/cap', data),
  update: (id: string, data: unknown) => apiClient.patch(`/cap/${id}`, data),
  close: (id: string) => apiClient.post(`/cap/${id}/close`),
}

// Trade Goods
export const tradeGoodsApi = {
  list: (params?: Record<string, unknown>) => apiClient.get('/trade-goods', { params }),
  get: (id: string) => apiClient.get(`/trade-goods/${id}`),
  create: (data: unknown) => apiClient.post('/trade-goods', data),
  update: (id: string, data: unknown) => apiClient.patch(`/trade-goods/${id}`, data),
}

// PCF
export const pcfApi = {
  list: (params?: Record<string, unknown>) => apiClient.get('/pcf', { params }),
  get: (id: string) => apiClient.get(`/pcf/${id}`),
  create: (data: unknown) => apiClient.post('/pcf', data),
  update: (id: string, data: unknown) => apiClient.patch(`/pcf/${id}`, data),
}

// Decarb
export const decarbApi = {
  list: (params?: Record<string, unknown>) => apiClient.get('/decarb', { params }),
  get: (id: string) => apiClient.get(`/decarb/${id}`),
  create: (data: unknown) => apiClient.post('/decarb', data),
  update: (id: string, data: unknown) => apiClient.patch(`/decarb/${id}`, data),
}

// Reports
export const reportsApi = {
  list: (params?: Record<string, unknown>) => apiClient.get('/reports', { params }),
  get: (id: string) => apiClient.get(`/reports/${id}`),
  create: (data: unknown) => apiClient.post('/reports', data),
  update: (id: string, data: unknown) => apiClient.patch(`/reports/${id}`, data),
}

// Dashboard
export const dashboardApi = {
  stats: () => apiClient.get('/dashboard/stats'),
  riskDistribution: () => apiClient.get('/dashboard/risk-distribution'),
  recentActivity: () => apiClient.get('/dashboard/recent-activity'),
}

// Auth
export const authApi = {
  login: (email: string, password: string) => apiClient.post('/auth/login', { email, password }),
  me: () => apiClient.get('/auth/me'),
}
