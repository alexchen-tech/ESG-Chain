import http from '@/api/http'

export interface SupplierContact {
  id: string
  name: string
  title: string | null
  email: string | null
  phone: string | null
  is_primary: boolean
}

export interface SupplierGroup {
  id: string
  name: string
}

export interface SasbIndustryRef {
  id: string
  code: string
  sector: string
  industry: string
}

export interface SupplierStatusHistory {
  id: string
  from_status: string
  to_status: string
  reason: string | null
  changed_by: string | null
  created_at: string
}

export interface SupplierOrganizationUnitRef {
  id: string
  name: string
  code: string
}

export interface SupplierOrganizationUnitHistory {
  id: string
  from_organization_unit_id: string | null
  to_organization_unit_id: string | null
  from_organization_unit: SupplierOrganizationUnitRef | null
  to_organization_unit: SupplierOrganizationUnitRef | null
  changed_by: string | null
  created_at: string
}

export interface Supplier {
  id: string
  name: string
  code: string | null
  country_code: string | null
  industry: string | null
  tier: number
  status: string
  onboarding_stage: string | null
  sasb_industry_id: string | null
  sasb_industry: SasbIndustryRef | null
  group_id: string | null
  group: SupplierGroup | null
  organization_unit_id: string | null
  organization_unit: SupplierOrganizationUnitRef | null
  organization_unit_histories?: SupplierOrganizationUnitHistory[]
  is_organization_unit_unassigned?: boolean
  contacts: SupplierContact[]
  status_histories: SupplierStatusHistory[]
  risk_score: number | null
  address: string | null
  website: string | null
  vat_number: string | null
  erp_vendor_codes: string | null
  spend_amount: number | null
  profile_completed: boolean
  created_at: string
}

export interface SupplierListParams {
  page?: number
  per_page?: number
  status?: string
  onboarding_stage?: string
  tier?: number
  q?: string
  supplier_group_id?: string
  country_code?: string
  sasb_industry_id?: string
  risk_dim?: string
  risk_probability?: string
  risk_impact?: string
  organization_unit_id?: string
}

export const suppliersApi = {
  list: (params?: SupplierListParams) =>
    http.get<{ success: boolean; data: Supplier[]; pagination: any }>('/api/v1/suppliers', { params }),

  get: (id: string) =>
    http.get<{ success: boolean; data: Supplier }>(`/api/v1/suppliers/${id}`),

  create: (data: Partial<Supplier> & { contacts?: any[] }) =>
    http.post<{ success: boolean; data: Supplier }>('/api/v1/suppliers', data),

  update: (id: string, data: {
    name?: string; code?: string | null; country_code?: string | null
    industry?: string | null; sasb_industry_id?: string | null
    tier?: number; group_id?: string | null; address?: string | null; website?: string | null
    vat_number?: string | null; erp_vendor_codes?: string | null; spend_amount?: number | null
  }) =>
    http.put<{ success: boolean; data: Supplier }>(`/api/v1/suppliers/${id}`, data),

  transition: (id: string, status: string, reason?: string) =>
    http.post<{ success: boolean; data: Supplier }>(`/api/v1/suppliers/${id}/transition`, { status, reason }),

  transitionOnboarding: (id: string, onboarding_stage: string, reason?: string) =>
    http.post<{ success: boolean; data: Supplier }>(`/api/v1/suppliers/${id}/onboarding-transition`, { onboarding_stage, reason }),

  updateOrganizationUnit: (id: string, organization_unit_id: string | null) =>
    http.patch<{ success: boolean; data: Supplier }>(`/api/v1/suppliers/${id}/organization-unit`, { organization_unit_id }),

  delete: (id: string) =>
    http.delete(`/api/v1/suppliers/${id}`),

  riskSummary: (id: string) =>
    http.get(`/api/v1/suppliers/${id}/risk-summary`),

  riskTimeline: (id: string) =>
    http.get<{ success: boolean; data: { events: any[]; pending_saq: any | null } }>(`/api/v1/suppliers/${id}/risk-timeline`),

  complianceDocs: (id: string) =>
    http.get<{ success: boolean; data: any[] }>(`/api/v1/suppliers/${id}/compliance-docs`),

  addContact: (id: string, data: { name: string; title?: string; email?: string; phone?: string; is_primary?: boolean }) =>
    http.post<{ success: boolean; data: SupplierContact }>(`/api/v1/suppliers/${id}/contacts`, data),

  updateContact: (supplierId: string, contactId: string, data: Partial<{ name: string; title: string | null; email: string | null; phone: string | null; is_primary: boolean }>) =>
    http.put<{ success: boolean; data: SupplierContact }>(`/api/v1/suppliers/${supplierId}/contacts/${contactId}`, data),

  deleteContact: (supplierId: string, contactId: string) =>
    http.delete(`/api/v1/suppliers/${supplierId}/contacts/${contactId}`),
}

// ─── 碳盤查覆蓋度稽核 ────────────────────────────────────────────────────────

export interface SupplierGhgScopeValue {
  period_year: number
  numeric_value: number
}

export interface SupplierGhgCoverageItem {
  supplier_id: string
  supplier_name: string
  coverage_level: '未盤查' | '僅範疇一二' | '含範疇三'
  scope1: SupplierGhgScopeValue | null
  scope2: SupplierGhgScopeValue | null
  scope3: SupplierGhgScopeValue | null
}

export interface SupplierGhgCoverageBucket {
  group_id: string | null
  group_name: string
  total: number
  not_surveyed_count: number
  partial_count: number
  full_count: number
  coverage_rate: number
}

export interface SupplierGhgCoverageOverall {
  total: number
  not_surveyed_count: number
  partial_count: number
  full_count: number
  coverage_rate: number
}

export interface SupplierGhgCoverageByGroup {
  groups: SupplierGhgCoverageBucket[]
  overall: SupplierGhgCoverageOverall
}

export interface SupplierGhgCoverageTrendPoint {
  period_year: number
  total_suppliers: number
  not_surveyed_count: number
  partial_count: number
  full_count: number
  coverage_rate: number
}

export interface SupplierGhgCoverageListParams {
  periodYear?: number
  sortBy?: 'scope1' | 'scope2' | 'scope3'
  sortDir?: 'asc' | 'desc'
  page?: number
  perPage?: number
}

export interface Pagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export const supplierGhgCoverageApi = {
  list: (params: SupplierGhgCoverageListParams = {}) =>
    http.get<{ success: boolean; data: SupplierGhgCoverageItem[]; pagination: Pagination }>(
      '/api/v1/suppliers/ghg-coverage',
      {
        params: {
          period_year: params.periodYear,
          sort_by: params.sortBy,
          sort_dir: params.sortDir,
          page: params.page ?? 1,
          per_page: params.perPage ?? 20,
        },
      }
    ),
  byGroup: (periodYear?: number) =>
    http.get<{ success: boolean; data: SupplierGhgCoverageByGroup }>('/api/v1/suppliers/ghg-coverage/by-group', {
      params: periodYear ? { period_year: periodYear } : undefined,
    }),
  trend: () =>
    http.get<{ success: boolean; data: SupplierGhgCoverageTrendPoint[] }>('/api/v1/suppliers/ghg-coverage/trend'),
}

export const supplierGroupsApi = {
  list: () => http.get<{ success: boolean; data: SupplierGroup[] }>('/api/v1/settings/supplier-groups'),
}

export const supplierProfileApi = {
  update: (supplierId: string, data: { sustainability_email: string; address: string }) =>
    http.put<{ success: boolean; data: Supplier }>(`/api/v1/suppliers/${supplierId}/profile`, data),
}

// ─── 活動資料層 ────────────────────────────────────────────────────────────────

export interface SupplierFacility {
  id: string
  supplier_id: string
  name: string
  country: string | null
  address: string | null
  facility_type: 'manufacturing' | 'warehouse' | 'office' | 'other' | 'weaving' | 'knitting' | 'dyeing' | 'printing' | 'wet_processing' | 'garment_assembly'
  energy_types: string[] | null
  main_products: string | null
  is_active: boolean
  latest_report_status: 'draft' | 'submitted' | 'verified' | null
  latest_report_period: string | null
  created_at: string
  updated_at: string
}

export interface ActivityDataReport {
  id: string
  supplier_facility_id: string
  report_period: string
  electricity_kwh: number | null
  natural_gas_gj: number | null
  fuel_oil_l: number | null
  heat_gj: number | null
  water_m3: number | null
  notes: string | null
  status: 'draft' | 'submitted' | 'verified'
  push_log: Record<string, unknown> | null
  submitted_at: string | null
  verified_at: string | null
  facility?: { id: string; name: string }
}

export const facilityApi = {
  list: (supplierId: string) =>
    http.get<{ data: SupplierFacility[] }>(`/api/v1/suppliers/${supplierId}/facilities`),

  create: (supplierId: string, data: Partial<SupplierFacility>) =>
    http.post<{ data: SupplierFacility }>(`/api/v1/suppliers/${supplierId}/facilities`, data),

  update: (supplierId: string, facilityId: string, data: Partial<SupplierFacility>) =>
    http.patch<{ data: SupplierFacility }>(`/api/v1/suppliers/${supplierId}/facilities/${facilityId}`, data),
}

export const activityReportApi = {
  list: (supplierId: string) =>
    http.get<{ data: ActivityDataReport[] }>(`/api/v1/suppliers/${supplierId}/activity-reports`),

  verify: (supplierId: string, reportId: string) =>
    http.post<{ data: ActivityDataReport }>(`/api/v1/suppliers/${supplierId}/activity-reports/${reportId}/verify`),

  push: (supplierId: string, reportId: string) =>
    http.post<{ success: boolean }>(`/api/v1/suppliers/${supplierId}/activity-reports/${reportId}/push`),
}

export interface ActivityReportDashboardItem extends ActivityDataReport {
  facility?: { id: string; name: string; supplier_id?: string; supplier?: { id: string; name: string } }
}

export interface ActivityReportSummary {
  total: number
  by_status: { draft: number; submitted: number; verified: number }
  by_push_status: { not_pushed: number; success: number; failed: number }
}

export const activityReportDashboardApi = {
  list: (params: { status?: string; push_status?: string; report_period?: string; supplier_id?: string; page?: number; per_page?: number }) =>
    http.get<{ data: ActivityReportDashboardItem[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }>('/api/v1/activity-reports', { params }),

  summary: () =>
    http.get<{ data: ActivityReportSummary }>('/api/v1/activity-reports/summary'),

  verify: (reportId: string) =>
    http.post<{ data: ActivityDataReport }>(`/api/v1/activity-reports/${reportId}/verify`),

  push: (reportId: string) =>
    http.post<{ success: boolean }>(`/api/v1/activity-reports/${reportId}/push`),
}

export const portalFacilityApi = {
  list: () =>
    http.get<{ data: SupplierFacility[] }>('/api/v1/portal/facilities'),

  createReport: (facilityId: string, data: Partial<ActivityDataReport>) =>
    http.post<{ data: ActivityDataReport }>(`/api/v1/portal/facilities/${facilityId}/activity-reports`, data),

  submitReport: (facilityId: string, reportId: string) =>
    http.post<{ data: ActivityDataReport }>(`/api/v1/portal/facilities/${facilityId}/activity-reports/${reportId}/submit`),
}

// ─── 化學合規 ────────────────────────────────────────────────────────────────

export interface MaterialItemChemical {
  id: string
  material_item_id: string
  cas_no: string
  weight_percentage: number | null
  reporting_threshold: number | null
  source: 'portal_supplier' | 'buyer_input' | 'ai_estimated'
  chemical?: {
    id: string
    cas_no: string
    substance_name: string
    iupac_name: string | null
    regulated_lists: Record<string, unknown> | null
    svhc_date: string | null
  }
  created_at: string
}

export interface ChemicalComplianceAlert {
  id: string
  material_item_id: string
  sales_product_id: string | null
  regulated_list: string
  alert_level: 'info' | 'warning' | 'critical'
  status: 'open' | 'acknowledged' | 'resolved'
  notes: string | null
  acknowledged_at: string | null
  material_item?: { id: string; name: string; item_code: string }
  chemical?: { id: string; cas_no: string; substance_name: string }
  created_at: string
}

export const chemicalApi = {
  listChemicals: (materialItemId: string) =>
    http.get<{ success: boolean; data: MaterialItemChemical[] }>(`/api/v1/material-items/${materialItemId}/chemicals`),

  addChemical: (materialItemId: string, data: { cas_no: string; weight_percentage?: number; reporting_threshold?: number; source?: string }) =>
    http.post<{ success: boolean; data: MaterialItemChemical }>(`/api/v1/material-items/${materialItemId}/chemicals`, data),

  deleteChemical: (materialItemId: string, chemicalId: string) =>
    http.delete(`/api/v1/material-items/${materialItemId}/chemicals/${chemicalId}`),

  lookup: (casNo: string) =>
    http.get<{ success: boolean; data: MaterialItemChemical['chemical'] }>(`/api/v1/chemicals/lookup?cas_no=${encodeURIComponent(casNo)}`),

  search: (q: string) =>
    http.get<{ success: boolean; data: MaterialItemChemical['chemical'][] }>(`/api/v1/chemicals/search?q=${encodeURIComponent(q)}`),

  scan: (materialItemId: string) =>
    http.post<{ success: boolean; new_alerts: number; data: ChemicalComplianceAlert[] }>(`/api/v1/material-items/${materialItemId}/chemical-compliance-scan`),

  listAlerts: (params?: { status?: string; material_item_id?: string; per_page?: number }) =>
    http.get<{ success: boolean; data: { data: ChemicalComplianceAlert[]; total: number } }>('/api/v1/chemical-compliance-alerts', { params }),

  acknowledgeAlert: (alertId: string, notes?: string) =>
    http.post<{ success: boolean; data: ChemicalComplianceAlert }>(`/api/v1/chemical-compliance-alerts/${alertId}/acknowledge`, { notes }),
}

export interface SupplierUser {
  id: string
  name: string
  email: string
  supplier_id: string
  is_active: boolean
  roles: string[]
  created_at: string
}

export const supplierUsersApi = {
  list: (supplierId: string) =>
    http.get<{ success: boolean; data: SupplierUser[] }>(`/api/v1/suppliers/${supplierId}/users`),

  invite: (supplierId: string, data: { name: string; email: string; role: 'supplier' | 'sup_esg' }) =>
    http.post<{ success: boolean; data: SupplierUser; message: string }>(`/api/v1/suppliers/${supplierId}/users`, data),
}
