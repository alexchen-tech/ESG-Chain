import { defineStore } from 'pinia'
import { authApi, type AuthResponse } from '@/api/modules/auth'
import http from '@/api/http'

interface AuthState {
  accessToken: string | null
  refreshTokenValue: string | null
  user: AuthResponse['user'] | null
  supplier: { id: string; name: string; vat_number: string | null; country_code: string | null; profile_completed: boolean; contacts?: any[] } | null
  isLoading: boolean
}

const SUPPLIER_ROLES = ['supplier', 'sup_esg']

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    accessToken: localStorage.getItem('access_token'),
    refreshTokenValue: localStorage.getItem('refresh_token'),
    user: null,
    supplier: null,
    isLoading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.accessToken,
    isSupplier: (state) => SUPPLIER_ROLES.includes(state.user?.role ?? ''),
    userRole: (state) => state.user?.role ?? null,
  },

  actions: {
    async login(email: string, password: string) {
      this.isLoading = true
      try {
        const { data } = await authApi.login({ email, password })
        this.setToken(data.data.access_token, data.data.refresh_token)
        this.user = data.data.user

        // 供應商角色：取得 supplier 主檔並導向 portal
        if (SUPPLIER_ROLES.includes(this.user.role) && this.user.supplier_id) {
          try {
            const { data } = await http.get(`/api/v1/suppliers/${this.user.supplier_id}`)
            this.supplier = data.data
          } catch { /**/ }
          return '/supplier/portal'
        }
        return '/dashboard'
      } finally {
        this.isLoading = false
      }
    },

    async fetchMe() {
      const { data } = await authApi.me()
      this.user = data.data
    },

    async refreshToken(): Promise<string> {
      if (!this.refreshTokenValue) throw new Error('No refresh token')
      const { data } = await authApi.refresh(this.refreshTokenValue)
      this.setToken(data.data.access_token, data.data.refresh_token)
      return data.data.access_token
    },

    logout() {
      authApi.logout().catch(() => {})
      this.clearAuth()
    },

    setToken(token: string, refreshToken?: string) {
      this.accessToken = token
      localStorage.setItem('access_token', token)
      if (refreshToken) {
        this.refreshTokenValue = refreshToken
        localStorage.setItem('refresh_token', refreshToken)
      }
    },

    clearAuth() {
      this.accessToken = null
      this.refreshTokenValue = null
      this.user = null
      this.supplier = null
      localStorage.removeItem('access_token')
      localStorage.removeItem('refresh_token')
    },
  },
})
