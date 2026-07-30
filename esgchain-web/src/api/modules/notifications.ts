import http from '@/api/http'
import type { NotificationItem, NotificationPagination } from './portalNotifications'

export type { NotificationItem, NotificationPagination }

export const notificationsApi = {
  list: (page = 1, perPage = 20) =>
    http.get<{ success: boolean; data: NotificationItem[]; pagination: NotificationPagination }>('/api/v1/notifications', {
      params: { page, per_page: perPage },
    }),

  unreadCount: () =>
    http.get<{ success: boolean; data: { unread_count: number } }>('/api/v1/notifications/unread-count'),

  markRead: () =>
    http.post<{ success: boolean; message: string }>('/api/v1/notifications/mark-read'),

  markOneRead: (id: string) =>
    http.post<{ success: boolean; message: string }>(`/api/v1/notifications/${id}/mark-read`),
}
