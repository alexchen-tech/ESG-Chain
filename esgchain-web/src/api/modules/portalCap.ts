import http from '@/api/http'
import type { CAP, CAPFinding, CAPUpdateEntry, CAPAttachment } from './cap'

export type { CAP, CAPFinding, CAPUpdateEntry, CAPAttachment }

export const portalCapApi = {
  list: (params?: { page?: number }) =>
    http.get<{ success: boolean; data: CAP[]; pagination: any }>('/api/v1/portal/caps', { params }),

  get: (id: string) =>
    http.get<{ success: boolean; data: CAP }>(`/api/v1/portal/caps/${id}`),

  addUpdate: (id: string, data: { status: 'in_progress' | 'completed'; notes?: string }) =>
    http.post<{ success: boolean; data: CAP; message: string }>(`/api/v1/portal/caps/${id}/update`, data),

  uploadAttachment: (capId: string, file: File, capUpdateId?: string) => {
    const form = new FormData()
    form.append('file', file)
    if (capUpdateId) form.append('cap_update_id', capUpdateId)
    return http.post<{ success: boolean; data: CAPAttachment }>(`/api/v1/caps/${capId}/attachments`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  deleteAttachment: (attachmentId: string) =>
    http.delete(`/api/v1/cap-attachments/${attachmentId}`),

  downloadAttachment: (attachmentId: string) =>
    http.get(`/api/v1/cap-attachments/${attachmentId}/download`, { responseType: 'blob' }),
}
