import http from '@/api/http'
import type { SaqDomain } from './settings'

export interface SaqProject {
  id: string
  name: string
  template_id: string
  status: 'draft' | 'active' | 'closed'
  closed_at: string | null
  start_date: string | null
  due_date: string | null
  description: string | null
  domain: SaqDomain | null
  is_comparable: boolean | null
  template_version: string | null
  template?: any
  series?: any
  created_at: string
  updated_at: string
}

export interface SaqProjectPagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface SaqProjectStatusCounts {
  all: number
  draft: number
  active: number
  closed: number
}

export const saqProjectApi = {
  list: (params?: Record<string, any>) =>
    http.get<{ success: boolean; data: SaqProject[]; pagination: SaqProjectPagination; status_counts: SaqProjectStatusCounts }>('/api/v1/saq-projects', { params }),

  get: (id: string) =>
    http.get<{ success: boolean; data: SaqProject }>(`/api/v1/saq-projects/${id}`),

  create: (data: { name: string; template_id: string; due_date?: string | null; description?: string | null; domain?: SaqDomain | null; series_id?: string | null }) =>
    http.post<{ success: boolean; data: SaqProject }>('/api/v1/saq-projects', data),

  update: (id: string, data: Partial<Omit<SaqProject, 'id' | 'created_at' | 'updated_at' | 'template'>>) =>
    http.put<{ success: boolean; data: SaqProject }>(`/api/v1/saq-projects/${id}`, data),

  remove: (id: string) =>
    http.delete(`/api/v1/saq-projects/${id}`),

  send: (id: string, supplierIds: string[]) =>
    http.post<{ success: boolean; created: number; skipped: number; data: SAQ[] }>(
      `/api/v1/saq-projects/${id}/send`, { supplier_ids: supplierIds }),

  close: (id: string) =>
    http.post<{ success: boolean; data: SaqProject }>(`/api/v1/saq-projects/${id}/close`),

  saqs: (id: string) =>
    http.get<{ success: boolean; data: SAQ[] }>(`/api/v1/saq-projects/${id}/saqs`),

  questions: (id: string) =>
    http.get<{ success: boolean; data: ProjectQuestion[] }>(`/api/v1/saq-projects/${id}/questions`),

  updateWeights: (id: string, weights: Array<{ id: string; weight: number }>) =>
    http.patch<{ success: boolean; message: string; rescoring_count: number; data: ProjectQuestion[] }>(
      `/api/v1/saq-projects/${id}/question-weights`, { weights }),
}

export interface ProjectQuestion {
  id: string
  order: number
  question_text: string
  question_type: string
  weight: number | null
  is_required: boolean
  source_template_question_id: string | null
  scoring_direction?: 'positive' | 'negative'
  scoring_type?: 'ordered_asc' | 'ordered_desc' | 'custom' | 'evidence_only' | 'llm' | null
  option_scores?: Record<string, number> | null
  options?: string[] | null
  tags?: string[] | null
}

export interface SaqResponse {
  id: string
  saq_id: string
  project_question_id: string
  answer: string | null
  answer_options: string[] | null
  evidence_note: string | null
  raw_score: number | null
  llm_score: number | null
  llm_score_reason: string | null
  score_confidence: 'high' | 'medium' | 'low' | null
}

export interface SAQ {
  id: string
  project_id: string
  supplier_id: string
  template_id: string
  status: string
  score: number | null
  grade: string | null
  sent_at: string | null
  submitted_at: string | null
  reviewed_at: string | null
  supplier?: any
}

export const saqApi = {
  listByProject: (projectId: string) =>
    http.get<{ success: boolean; data: SAQ[] }>(`/api/v1/saq-projects/${projectId}/saqs`),

  myList: () =>
    http.get<{ success: boolean; data: SAQ[]; pagination: any }>('/api/v1/saqs/my'),

  get: (id: string) =>
    http.get<{ success: boolean; data: SAQ }>(`/api/v1/saqs/${id}`),

  send: (projectId: string, supplierId: string) =>
    http.post(`/api/v1/saq-projects/${projectId}/saqs/send`, { supplier_id: supplierId }),

  submit: (id: string, responses: any[]) =>
    http.post(`/api/v1/saqs/${id}/submit`, { responses }),

  approve: (id: string, comment?: string) =>
    http.post(`/api/v1/saqs/${id}/approve`, { comment }),

  reject: (id: string, comment: string) =>
    http.post(`/api/v1/saqs/${id}/reject`, { comment }),

  startReview: (id: string) =>
    http.post<{ success: boolean; data: SAQ }>(`/api/v1/saqs/${id}/start-review`),

  completeReview: (id: string, comment?: string) =>
    http.post<{ success: boolean; data: SAQ }>(`/api/v1/saqs/${id}/complete-review`, { comment }),

  returnReview: (id: string, comment: string) =>
    http.post<{ success: boolean; data: SAQ }>(`/api/v1/saqs/${id}/return-review`, { comment }),

  markReviewed: (id: string) =>
    http.post<{ success: boolean; data: SAQ }>(`/api/v1/saqs/${id}/mark-reviewed`),

  // saq-scoring-v2: 題目層覆核
  getResponseReviews: (id: string) =>
    http.get<{ success: boolean; data: any[] }>(`/api/v1/saqs/${id}/response-reviews`),

  submitResponseReviews: (id: string, reviews: Array<{ project_question_id: string; reviewer_score: number; reason?: string }>) =>
    http.post<{ success: boolean; data: SAQ }>(`/api/v1/saqs/${id}/response-reviews`, { reviews }),

  // saq-scoring-v2: 計分快照
  getScoreSnapshots: (id: string) =>
    http.get<{ success: boolean; data: any[] }>(`/api/v1/saqs/${id}/score-snapshots`),

  // saq-scoring-v2: 申訴流程（審核員）
  startReReview: (id: string) =>
    http.post<{ success: boolean; data: SAQ }>(`/api/v1/saqs/${id}/re-review`),

  finalize: (id: string, comment?: string) =>
    http.post<{ success: boolean; data: SAQ }>(`/api/v1/saqs/${id}/finalize`, { comment }),
}

export interface AssessmentSeries {
  id: string
  name: string
  description: string | null
  domain: string | null
  template_id: string
  template_version_at_creation: string | null
  template?: { id: string; name: string; scoring_framework: string | null }
  status: 'active' | 'archived'
  created_by_id: string | null
  projects_count?: number
  latest_project_date?: string | null
  has_mixed_versions?: boolean
  comparable_versions_count?: number
  pillar_weights: Record<string, number> | null
  grade_thresholds: Record<string, number> | null
  created_at: string
  updated_at: string
}

export interface SeriesScoringConfig {
  pillar_weights: Record<string, number> | null
  grade_thresholds: Record<string, number> | null
  available_pillars: Array<{ slug: string; label: string }>
  dim_weights: Record<string, number> | null
  dim_weights_source: 'default' | 'custom'
  e4_objective_ratio: number
}

export interface AssessmentSeriesWeight {
  id: string
  series_id: string
  source_template_question_id: string
  weight: number
}

export interface SeriesComparisonProject {
  id: string
  name: string
  created_at: string
  scoring_model_id?: string | null
}

export interface SeriesQuestionTrend {
  source_template_question_id: string
  question_text: string
  scores: Record<string, number | null>
}

export interface SeriesSupplierComparison {
  supplier_id: string
  supplier_name?: string
  scores_by_project: Record<string, { total_score: number | null; grade: string | null } | null>
  question_trends: SeriesQuestionTrend[]
}

export interface SeriesComparisonResponse {
  series_id: string
  scoring_model_inconsistent: boolean
  projects: SeriesComparisonProject[]
  suppliers: SeriesSupplierComparison[]
}

export const assessmentSeriesApi = {
  list: () =>
    http.get<{ success: boolean; data: AssessmentSeries[] }>('/api/v1/assessment-series'),

  create: (data: { name: string; template_id: string; description?: string | null }) =>
    http.post<{ success: boolean; data: AssessmentSeries }>('/api/v1/assessment-series', data),

  get: (id: string) =>
    http.get<{ success: boolean; data: AssessmentSeries }>(`/api/v1/assessment-series/${id}`),

  update: (id: string, data: { name?: string; description?: string | null; domain?: string | null }) =>
    http.put<{ success: boolean; data: AssessmentSeries }>(`/api/v1/assessment-series/${id}`, data),

  archive: (id: string) =>
    http.post<{ success: boolean; data: AssessmentSeries }>(`/api/v1/assessment-series/${id}/archive`),

  getWeights: (id: string) =>
    http.get<{ success: boolean; data: AssessmentSeriesWeight[] }>(`/api/v1/assessment-series/${id}/weights`),

  setWeights: (id: string, weights: Array<{ source_template_question_id: string; weight: number }>) =>
    http.put<{ success: boolean; data: AssessmentSeriesWeight[] }>(`/api/v1/assessment-series/${id}/weights`, { weights }),

  getProjects: (id: string) =>
    http.get<{ success: boolean; data: any[] }>(`/api/v1/assessment-series/${id}/projects`),

  getComparison: (id: string, supplierIds: string[]) =>
    http.get<{ success: boolean; data: SeriesComparisonResponse }>(`/api/v1/assessment-series/${id}/comparison`, {
      params: { supplier_ids: supplierIds },
    }),

  getScoringConfig: (id: string) =>
    http.get<{ success: boolean; data: SeriesScoringConfig }>(`/api/v1/assessment-series/${id}/scoring-config`),

  updateScoringConfig: (id: string, data: { pillar_weights?: Record<string, number> | null; grade_thresholds?: Record<string, number> | null; dim_weights?: Record<string, number> | null; e4_objective_ratio?: number }) =>
    http.put<{ success: boolean; data: AssessmentSeries; message: string }>(`/api/v1/assessment-series/${id}/scoring-config`, data),
}

export interface FrameworkDefaultWeight {
  pillar_slug: string
  weight: number
  sort_order: number
}

export interface SasbRequiredTopic {
  id: string
  tag_slug: string
  label_zh: string | null
  rationale: string | null
}

export const frameworkDefaultWeightsApi = {
  getAll: () =>
    http.get<{ data: Record<string, FrameworkDefaultWeight[]>; pillars: Record<string, string[]> }>('/api/v1/settings/framework-default-weights'),

  update: (framework: string, weights: Array<{ pillar_slug: string; weight: number }>) =>
    http.put<{ message: string }>(`/api/v1/settings/framework-default-weights/${framework}`, { weights }),
}

export const sasbRequiredTopicsApi = {
  getAll: () =>
    http.get<{ data: Record<string, SasbRequiredTopic[]> }>('/api/v1/settings/sasb-required-topics'),

  create: (data: { sasb_industry_code: string; tag_slug: string; rationale?: string }) =>
    http.post<{ data: SasbRequiredTopic }>('/api/v1/settings/sasb-required-topics', data),

  delete: (id: string) =>
    http.delete<{ message: string }>(`/api/v1/settings/sasb-required-topics/${id}`),
}
