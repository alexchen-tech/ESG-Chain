import http from '@/api/http'

export interface TopicScore {
  topic_name: string
  score: number
}

export interface TemplateRecommendation {
  id: string
  name: string
  version: string
  match_type: 'exact' | 'sector' | 'generic' | 'incompatible'
}

export interface Questionnaire {
  id: string
  supplier_id: string
  template_id: string
  project_id: string | null
  status: string
  is_editable: boolean
  round: number
  deadline: string | null
  submitted_at: string | null
  review_started_at: string | null
  reviewed_by_id: string | null
  completed_at: string | null
  scoring_job_id: string | null
  score: number | null
  grade: string | null
  supplier?: any
  template?: any
  project?: any
  reviewer?: any
  responses?: any[]
  review_histories?: any[]
}

export interface QuestionnaireCounts {
  just_submitted_count: number
  under_review_count: number
  overdue_count: number
  submitted_count: number
}

export const questionnaireApi = {
  list: (params?: Record<string, any>) =>
    http.get<{ success: boolean; data: Questionnaire[]; pagination: any }>('/api/v1/questionnaires', { params }),

  counts: () =>
    http.get<{ success: boolean; data: QuestionnaireCounts }>('/api/v1/questionnaires/counts'),

  get: (id: string) =>
    http.get<{ success: boolean; data: Questionnaire }>(`/api/v1/questionnaires/${id}`),

  send: (data: { supplier_ids: string[]; template_id: string; deadline?: string; project_id?: string }) =>
    http.post('/api/v1/questionnaires/send', data),

  update: (id: string, answers: Record<string, any>) =>
    http.put(`/api/v1/questionnaires/${id}`, { answers }),

  submit: (id: string) =>
    http.post(`/api/v1/questionnaires/${id}/submit`),

  startReview: (id: string) =>
    http.post(`/api/v1/questionnaires/${id}/start-review`),

  completeReview: (id: string, comment?: string) =>
    http.post(`/api/v1/questionnaires/${id}/complete-review`, { comment }),

  returnReview: (id: string, comment: string) =>
    http.post(`/api/v1/questionnaires/${id}/return-review`, { comment }),

  markReviewed: (id: string) =>
    http.post(`/api/v1/questionnaires/${id}/mark-reviewed`),

  // saq-scoring-v2: 供應商申訴
  dispute: (id: string, reason: string) =>
    http.post(`/api/v1/questionnaires/${id}/dispute`, { reason }),
}

export const recommendTemplatesApi = {
  recommend: (supplierIds: string[]) =>
    http.post<{ success: boolean; data: Record<string, TemplateRecommendation[]> }>(
      '/api/v1/questionnaires/recommend-templates', { supplier_ids: supplierIds }),
}
