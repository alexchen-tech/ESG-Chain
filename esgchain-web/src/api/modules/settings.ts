import http from '@/api/http'

export type QuestionType = 'single_choice' | 'multiple_choice' | 'text' | 'number' | 'boolean'
export type SaqDomain = 'ESG' | 'ISO20400' | 'Geo-Risk' | 'Product-Compliance'

export interface QuestionTag {
  id: string
  l1_domain: string
  l2_pillar: string
  l3_topic: string
  slug: string
  label_zh: string | null
  label_en: string | null
  scoring_engine_key: string | null
  deprecated_at: string | null
  sort_order: number
}

export interface TagTreeTopic extends QuestionTag {}

export interface TagTreePillar {
  l2_pillar: string
  topics: TagTreeTopic[]
}

export interface TagTreeDomain {
  l1_domain: string
  pillars: TagTreePillar[]
}

export interface DisclosureField {
  slug: string
  label: string
  data_type: 'boolean' | 'numeric'
  unit: string | null
}

export interface QuestionBankItem {
  id: string
  template_id: null
  question_text: string
  question_type: QuestionType
  options: string[] | null
  weight: number | null
  order: number
  is_required: boolean
  sasb_topic_id: string | null
  sasb_metric_code: string | null
  tags: string[] | null
  source_bank_question_id: null
  usage_count: number
  question_tags: QuestionTag[]
  disclosure_field_slug: string | null
  created_at: string
  updated_at: string
}

export const questionBankApi = {
  list: (params?: { l1?: string; l2?: string; l3?: string; keyword?: string }) =>
    http.get<{ success: boolean; data: QuestionBankItem[] }>(
      '/api/v1/settings/question-bank', { params }),

  create: (data: {
    question_text: string; question_type: QuestionType
    options?: string[] | null; weight?: number | null; is_required?: boolean
    sasb_topic_id?: string | null; sasb_metric_code?: string | null
    tags?: string[] | null; tag_ids?: string[]
  }) =>
    http.post<{ success: boolean; data: QuestionBankItem }>(
      '/api/v1/settings/question-bank', data),

  update: (id: string, data: Partial<QuestionBankItem> & { tag_ids?: string[] }) =>
    http.put<{ success: boolean; data: QuestionBankItem }>(
      `/api/v1/settings/question-bank/${id}`, data),

  remove: (id: string) =>
    http.delete(`/api/v1/settings/question-bank/${id}`),

  listTags: (questionId: string) =>
    http.get<{ success: boolean; data: QuestionTag[] }>(
      `/api/v1/settings/question-bank/${questionId}/tags`),

  addTag: (questionId: string, tagId: string) =>
    http.post(`/api/v1/settings/question-bank/${questionId}/tags`, { tag_id: tagId }),

  removeTag: (questionId: string, tagId: string) =>
    http.delete(`/api/v1/settings/question-bank/${questionId}/tags/${tagId}`),
}

export const tagLibraryApi = {
  tree: () =>
    http.get<{ success: boolean; data: TagTreeDomain[] }>('/api/v1/settings/tag-library/tree'),

  list: (params?: { l1?: string; l2?: string; include_deprecated?: boolean }) =>
    http.get<{ success: boolean; data: QuestionTag[] }>('/api/v1/settings/tag-library', { params }),

  create: (data: {
    l1_domain: string; l2_pillar: string; l3_topic: string
    slug?: string; label_zh?: string; label_en?: string
    scoring_engine_key?: string | null; sort_order?: number
  }) =>
    http.post<{ success: boolean; data: QuestionTag }>('/api/v1/settings/tag-library', data),

  update: (id: string, data: {
    l1_domain?: string; l2_pillar?: string; l3_topic?: string
    label_zh?: string; label_en?: string
    scoring_engine_key?: string | null; sort_order?: number
  }) =>
    http.put<{ success: boolean; data: QuestionTag }>(`/api/v1/settings/tag-library/${id}`, data),

  deprecate: (id: string) =>
    http.post(`/api/v1/settings/tag-library/${id}/deprecate`),

  restore: (id: string) =>
    http.post(`/api/v1/settings/tag-library/${id}/restore`),

  getL2Nodes: () =>
    http.get<{ success: boolean; data: Record<string, QuestionTag[]> }>('/api/v1/settings/tag-library/l2-nodes'),

  updateL2NodeWeight: (id: string, defaultWeight: number) =>
    http.put<{ success: boolean; data: QuestionTag }>(`/api/v1/settings/tag-library/l2-nodes/${id}/weight`, {
      default_weight: defaultWeight,
    }),
}

export const importFromBankApi = {
  import: (templateId: string, questionIds: string[]) =>
    http.post<{ success: boolean; data: any[]; message: string }>(
      `/api/v1/settings/questionnaire-templates/${templateId}/import-from-bank`,
      { question_ids: questionIds }),
}

export interface SasbDisclosureTopic {
  id: string
  sasb_industry_id: string
  topic_name: string
  topic_code: string
  esg_category: QuestionCategory
  description: string | null
}

export interface ScoringModel {
  id: string
  name: string
  sasb_industry_code: string | null
  weight_e: number
  weight_s: number
  weight_g: number
  grade_a_threshold: number
  grade_b_threshold: number
  grade_c_threshold: number
  grade_d_threshold: number
  is_active: boolean
  description: string | null
}

export const sasbTopicsApi = {
  list: (params?: { industry_id?: string; esg_category?: QuestionCategory }) =>
    http.get<{ success: boolean; data: SasbDisclosureTopic[] }>(
      '/api/v1/settings/sasb-topics', { params }),
}

export const scoringModelsApi = {
  list: (params?: { industry_code?: string; is_active?: boolean }) =>
    http.get<{ success: boolean; data: ScoringModel[] }>(
      '/api/v1/settings/scoring-models', { params }),

  create: (data: {
    name: string; sasb_industry_code?: string | null
    weight_e: number; weight_s: number; weight_g: number
    grade_a_threshold?: number; grade_b_threshold?: number
    grade_c_threshold?: number; grade_d_threshold?: number
    description?: string | null; is_active?: boolean
  }) =>
    http.post<{ success: boolean; data: { id: string; name: string } }>(
      '/api/v1/settings/scoring-models', data),

  update: (id: string, data: Partial<ScoringModel>) =>
    http.put<{ success: boolean; data: { id: string } }>(
      `/api/v1/settings/scoring-models/${id}`, data),

  remove: (id: string) =>
    http.delete(`/api/v1/settings/scoring-models/${id}`),
}

export interface SAQQuestion {
  id: string
  template_id: string
  category: QuestionCategory
  question_text: string
  question_type: QuestionType
  options: string[] | null
  weight: number | null
  order: number
  is_required: boolean
  compliance_domains?: string[]
  created_at: string
  updated_at: string
}

export const questionsApi = {
  list: (templateId: string) =>
    http.get<{ success: boolean; data: SAQQuestion[] }>(
      `/api/v1/settings/questionnaire-templates/${templateId}/questions`),

  create: (templateId: string, data: {
    question_text: string; category: QuestionCategory; question_type: QuestionType
    options?: string[] | null; weight?: number | null; is_required?: boolean; order?: number
  }) =>
    http.post<{ success: boolean; data: SAQQuestion }>(
      `/api/v1/settings/questionnaire-templates/${templateId}/questions`, data),

  update: (templateId: string, questionId: string, data: {
    question_text?: string; category?: QuestionCategory; options?: string[] | null
    weight?: number | null; is_required?: boolean; order?: number
  }) =>
    http.put<{ success: boolean; data: SAQQuestion }>(
      `/api/v1/settings/questionnaire-templates/${templateId}/questions/${questionId}`, data),

  remove: (templateId: string, questionId: string) =>
    http.delete(`/api/v1/settings/questionnaire-templates/${templateId}/questions/${questionId}`),

  reorder: (templateId: string, questionIds: string[]) =>
    http.patch<{ success: boolean; data: SAQQuestion[] }>(
      `/api/v1/settings/questionnaire-templates/${templateId}/questions/reorder`,
      { question_ids: questionIds }),
}

export interface QuestionnaireTemplate {
  id: string
  name: string
  version: string | number
  status: 'draft' | 'published' | 'archived'
  draft_of: string | null
  has_draft?: boolean
  draft_id?: string | null
  description: string | null
  is_active: boolean
  archived_at: string | null
  sasb_industry_id: string | null
  created_by_id: string | null
  questions_count?: number
  created_at: string
  updated_at: string
}

export interface SupplierGroup {
  id: string
  name: string
  description: string | null
  required_doc_types: string[]
  created_at: string
  updated_at: string
}

export interface MarketDefinition {
  id: string
  code: string
  label: string
  description: string | null
  is_system: boolean
  created_at: string
  updated_at: string
}

export interface SasbIndustry {
  id: string
  sector: string
  industry: string
  code: string
}

export type OrgUnitType = 'headquarters' | 'subsidiary' | 'business_unit' | 'department' | 'branch'

export interface OrgUnit {
  id: string
  name: string
  code: string
  type: OrgUnitType
  parent_id: string | null
  country_code: string
  depth: number
  sort_order: number
  is_active: boolean
  children_list?: OrgUnit[]
  created_at: string
  updated_at: string
}

export const orgUnitsApi = {
  list: () =>
    http.get<{ success: boolean; data: OrgUnit[] }>('/api/v1/settings/org-units'),

  tree: () =>
    http.get<{ success: boolean; data: OrgUnit[] }>('/api/v1/settings/org-units/tree'),

  create: (data: { name: string; code: string; type: OrgUnitType; parent_id?: string | null; country_code?: string; sort_order?: number }) =>
    http.post<{ success: boolean; data: OrgUnit }>('/api/v1/settings/org-units', data),

  update: (id: string, data: { name?: string; code?: string; country_code?: string; is_active?: boolean; sort_order?: number }) =>
    http.put<{ success: boolean; data: OrgUnit }>(`/api/v1/settings/org-units/${id}`, data),

  remove: (id: string) =>
    http.delete(`/api/v1/settings/org-units/${id}`),
}

export const settingsApi = {
  templates: {
    list: (params?: { is_active?: boolean; is_archived?: boolean; sasb_industry_id?: string; page?: number; per_page?: number }) =>
      http.get<{ success: boolean; data: QuestionnaireTemplate[]; pagination: any }>(
        '/api/v1/settings/questionnaire-templates', { params }),

    get: (id: string) =>
      http.get<{ success: boolean; data: QuestionnaireTemplate }>(
        `/api/v1/settings/questionnaire-templates/${id}`),

    create: (data: { name: string; version: string; description?: string; sasb_industry_id?: string }) =>
      http.post<{ success: boolean; data: QuestionnaireTemplate }>(
        '/api/v1/settings/questionnaire-templates', data),

    update: (id: string, data: Partial<QuestionnaireTemplate>) =>
      http.put<{ success: boolean; data: QuestionnaireTemplate }>(
        `/api/v1/settings/questionnaire-templates/${id}`, data),

    delete: (id: string) =>
      http.delete(`/api/v1/settings/questionnaire-templates/${id}`),

    clone: (id: string) =>
      http.post<{ success: boolean; data: QuestionnaireTemplate }>(
        `/api/v1/settings/questionnaire-templates/${id}/clone`),

    archive: (id: string) =>
      http.post<{ success: boolean; data: QuestionnaireTemplate }>(
        `/api/v1/settings/questionnaire-templates/${id}/archive`),

    publish: (id: string) =>
      http.post<{ success: boolean; data: QuestionnaireTemplate }>(
        `/api/v1/settings/questionnaire-templates/${id}/publish`),

    unarchive: (id: string) =>
      http.post<{ success: boolean; data: QuestionnaireTemplate }>(
        `/api/v1/settings/questionnaire-templates/${id}/unarchive`),
  },

  groups: {
    list: () =>
      http.get<{ success: boolean; data: SupplierGroup[] }>('/api/v1/settings/supplier-groups'),

    create: (data: { name: string; description?: string; required_doc_types?: string[] }) =>
      http.post<{ success: boolean; data: SupplierGroup }>('/api/v1/settings/supplier-groups', data),

    update: (id: string, data: { name?: string; description?: string; required_doc_types?: string[] }) =>
      http.put<{ success: boolean; data: SupplierGroup }>(
        `/api/v1/settings/supplier-groups/${id}`, data),

    delete: (id: string) =>
      http.delete(`/api/v1/settings/supplier-groups/${id}`),
  },

  sasb: {
    list: (sector?: string) =>
      http.get<{ success: boolean; data: SasbIndustry[] }>(
        '/api/v1/settings/sasb-industries', { params: sector ? { sector } : {} }),
  },

  getDimWeightDefaults: () =>
    http.get<{ success: boolean; dim_weights: Record<string, number> }>(
      '/api/v1/settings/dim-weight-defaults'),

  updateDimWeightDefaults: (dimWeights: Record<string, number>) =>
    http.put<{ success: boolean; dim_weights: Record<string, number>; message: string }>(
      '/api/v1/settings/dim-weight-defaults', { dim_weights: dimWeights }),
}

export const supplierGroupsApi = {
  list: () =>
    http.get<{ success: boolean; data: SupplierGroup[] }>('/api/v1/supplier-groups'),

  inferredMaterialGroups: (id: string) =>
    http.get<{ success: boolean; data: { material_groups: any[]; compliance_domains: string[] } }>(
      `/api/v1/supplier-groups/${id}/inferred-material-groups`),
}

export const disclosureFieldsApi = {
  list: () =>
    http.get<{ success: boolean; data: DisclosureField[] }>('/api/v1/settings/disclosure-fields'),
}

export const marketDefinitionApi = {
  list: () =>
    http.get<{ success: boolean; data: MarketDefinition[] }>('/api/v1/market-definitions'),
  create: (data: { code: string; label: string; description?: string }) =>
    http.post<{ success: boolean; data: MarketDefinition }>('/api/v1/market-definitions', data),
  update: (id: string, data: { label?: string; description?: string }) =>
    http.put<{ success: boolean; data: MarketDefinition }>(`/api/v1/market-definitions/${id}`, data),
  destroy: (id: string) =>
    http.delete(`/api/v1/market-definitions/${id}`),
}
