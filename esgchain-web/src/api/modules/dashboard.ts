import http from '@/api/http'

export interface ActionCard {
  key: string
  label: string
  value: number
  link: string
  urgent: boolean
}

export interface DashboardSummary {
  cards: ActionCard[]
}

export interface RecentActivity {
  supplier_id: string
  supplier_name: string
  event_type: 'status_change' | 'saq_submitted' | 'cap_updated' | 'doc_expiring' | 'doc_uploaded'
  event_label: string
  occurred_at: string
  severity: 'info' | 'warning' | 'pending'
}

export interface ExpiringDoc {
  supplier_id: string
  supplier_name: string
  doc_type: string
  expires_at: string
  days_remaining: number
}

export interface ComplianceRisk {
  carbon_price_eur: number
  cbam_products_count: number
  cbam_total_emissions: number
  cbam_risk_amount_eur: number
  cbam_missing_emissions: number
  compliance_issues_count: number
  eudr_pending_count: number
}

export interface EsgAxisScore {
  avg_score: number | null
  level: string | null
  distribution: Record<string, number>
}

export interface EsgScoresData {
  axis1: EsgAxisScore | null
  axis2: EsgAxisScore | null
  axis3: EsgAxisScore | null
  supplier_count: number
}

export const dashboardApi = {
  summary: () =>
    http.get<{ success: boolean; data: DashboardSummary }>('/api/v1/dashboard/summary'),

  recentActivity: (params?: { days?: number; limit?: number }) =>
    http.get<{ success: boolean; data: RecentActivity[] }>('/api/v1/dashboard/recent-activity', { params }),

  expiringDocs: () =>
    http.get<{ success: boolean; data: ExpiringDoc[] }>('/api/v1/dashboard/expiring-docs'),

  complianceRisk: () =>
    http.get<{ success: boolean; data: ComplianceRisk }>('/api/v1/dashboard/compliance-risk'),

  esgScores: () =>
    http.get<{ success: boolean; data: EsgScoresData }>('/api/v1/dashboard/esg-scores'),
}
