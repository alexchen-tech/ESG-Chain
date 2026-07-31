import http from '@/api/http'

export interface LoginPayload {
  email: string
  password: string
}

export interface AuthResponse {
  access_token: string
  refresh_token: string
  token_type: string
  expires_in: number
  user: {
    id: string
    name: string
    email: string
    role: string
    supplierId: string | null
    permissions: string[]
  }
}

export const authApi = {
  login: (payload: LoginPayload) =>
    http.post<{ success: boolean; data: AuthResponse }>('/api/v1/auth/login', payload),

  me: () =>
    http.get<{ success: boolean; data: AuthResponse['user'] }>('/api/v1/auth/me'),

  refresh: (refreshToken: string) =>
    http.post<{ success: boolean; data: AuthResponse }>('/api/v1/auth/refresh', { refresh_token: refreshToken }),

  logout: () =>
    http.post('/api/v1/auth/logout'),
}
