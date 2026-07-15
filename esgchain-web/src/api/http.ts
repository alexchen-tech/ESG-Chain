import axios, { type AxiosInstance, type AxiosResponse } from 'axios'
import { useAuthStore } from '@/stores/auth'

const http: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8080',
  timeout: Number(import.meta.env.VITE_API_TIMEOUT) || 30000,
  headers: { 'Content-Type': 'application/json' },
})

// 換發中的佇列，避免多個 401 同時觸發多次換發
let isRefreshing = false
let failedQueue: Array<{ resolve: (v: string) => void; reject: (e: unknown) => void }> = []

function processQueue(error: unknown, token: string | null = null) {
  failedQueue.forEach(({ resolve, reject }) => {
    if (error) reject(error)
    else resolve(token!)
  })
  failedQueue = []
}

// Request 攔截器：自動帶入 Bearer Token
http.interceptors.request.use((config) => {
  const auth = useAuthStore()
  if (auth.accessToken) {
    config.headers.Authorization = `Bearer ${auth.accessToken}`
  }
  return config
})

// Response 攔截器
http.interceptors.response.use(
  (response: AxiosResponse) => response,
  async (error) => {
    const originalRequest = error.config
    const status = error.response?.status

    // 登入端點本身的 401 不觸發 refresh（credentials 錯誤，非 token 過期）
    const isAuthEndpoint = originalRequest.url?.includes('/auth/login') || originalRequest.url?.includes('/auth/refresh')
    if (status === 401 && !originalRequest._retry && !isAuthEndpoint) {
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject })
        }).then((token) => {
          originalRequest.headers.Authorization = `Bearer ${token}`
          return http(originalRequest)
        })
      }

      originalRequest._retry = true
      isRefreshing = true

      try {
        const auth = useAuthStore()
        const newToken = await auth.refreshToken()
        processQueue(null, newToken)
        originalRequest.headers.Authorization = `Bearer ${newToken}`
        return http(originalRequest)
      } catch (refreshError) {
        processQueue(refreshError, null)
        const auth = useAuthStore()
        auth.logout()
        window.location.href = '/login'
        return Promise.reject(refreshError)
      } finally {
        isRefreshing = false
      }
    }

    // 統一錯誤通知（422 交給呼叫方處理）
    if (status === 403) {
      console.error('權限不足')
    } else if (status === 429) {
      console.error('操作過於頻繁，請稍後再試')
    } else if (status === 500) {
      console.error('系統發生錯誤，請聯繫管理員')
    }

    return Promise.reject(error)
  }
)

export default http
