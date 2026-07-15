import http from '@/api/http'

export interface CAPFinding {
  id: string
  cap_id: string
  category: string | null
  framework: string | null
  topic_slug: string | null
  topic_label_zh: string | null
  source_score: number | null
  threshold: number | null
  finding: string
  root_cause: string | null
  corrective_action: string | null
  status: string
  target_date: string | null
}

export interface CAP {
  id: string
  supplier_id: string
  saq_id: string | null
  source_type: string | null
  source_id: string | null
  triggered_by_axis: 'axis1' | 'axis2' | 'axis3' | null
  auto_generated: boolean
  title: string
  description: string | null
  status: string
  priority: string
  due_date: string | null
  assigned_to: string | null
  supplier?: any
  findings?: CAPFinding[]
}

export const capApi = {
  list: (params?: { supplier_id?: string; status?: string; page?: number }) =>
    http.get<{ success: boolean; data: CAP[]; pagination: any }>('/api/v1/caps', { params }),

  get: (id: string) =>
    http.get<{ success: boolean; data: CAP }>(`/api/v1/caps/${id}`),

  create: (data: Partial<CAP> & { findings?: any[] }) =>
    http.post<{ success: boolean; data: CAP }>('/api/v1/caps', data),

  update: (id: string, data: Partial<CAP>) =>
    http.put<{ success: boolean; data: CAP }>(`/api/v1/caps/${id}`, data),

  delete: (id: string) =>
    http.delete(`/api/v1/caps/${id}`),
}
