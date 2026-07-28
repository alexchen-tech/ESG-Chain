import http from '@/api/http'

export const portalNotificationsApi = {
  unreadCount: () =>
    http.get<{ success: boolean; data: { unread_count: number } }>('/api/v1/portal/notifications/unread-count'),

  markRead: () =>
    http.post<{ success: boolean; message: string }>('/api/v1/portal/notifications/mark-read'),
}
