import http from '@/api/http'

export interface NotificationItem {
  id: string
  type: string
  data: {
    cap_id?: string
    title?: string
    priority?: string
    due_date?: string | null
    message: string
  }
  read_at: string | null
  created_at: string
}

export interface NotificationPagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export const portalNotificationsApi = {
  list: (page = 1, perPage = 20) =>
    http.get<{ success: boolean; data: NotificationItem[]; pagination: NotificationPagination }>('/api/v1/portal/notifications', {
      params: { page, per_page: perPage },
    }),

  unreadCount: () =>
    http.get<{ success: boolean; data: { unread_count: number } }>('/api/v1/portal/notifications/unread-count'),

  markRead: () =>
    http.post<{ success: boolean; message: string }>('/api/v1/portal/notifications/mark-read'),

  markOneRead: (id: string) =>
    http.post<{ success: boolean; message: string }>(`/api/v1/portal/notifications/${id}/mark-read`),
}
