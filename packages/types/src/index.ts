// ── Enums ────────────────────────────────────────────────────────────────────

export enum UserRole {
  ADMIN = 'admin',
  BUYER = 'buyer',
  SUSTAIN = 'sustain',
  COMPLY = 'comply',
  ANALYST = 'analyst',
  SUPPLIER = 'supplier',
  SUP_ESG = 'sup_esg',
}

export enum SupplierTier {
  TIER1 = 'tier1',
  TIER2 = 'tier2',
  TIER3 = 'tier3',
}

export enum SupplierStatus {
  ACTIVE = 'active',
  INACTIVE = 'inactive',
  PENDING = 'pending',
  SUSPENDED = 'suspended',
}

export enum RiskLevel {
  LOW = 'low',
  MEDIUM = 'medium',
  HIGH = 'high',
  CRITICAL = 'critical',
}

export enum SAQStatus {
  DRAFT = 'draft',
  SENT = 'sent',
  IN_PROGRESS = 'in_progress',
  SUBMITTED = 'submitted',
  REVIEWED = 'reviewed',
  CLOSED = 'closed',
}

export enum CAPStatus {
  OPEN = 'open',
  IN_PROGRESS = 'in_progress',
  VERIFIED = 'verified',
  CLOSED = 'closed',
  OVERDUE = 'overdue',
}

export enum PCFStatus {
  DRAFT = 'draft',
  SUBMITTED = 'submitted',
  VERIFIED = 'verified',
}

export enum DecarbStatus {
  BASELINE = 'baseline',
  PLANNING = 'planning',
  COMMITTED = 'committed',
  ON_TRACK = 'on_track',
  OFF_TRACK = 'off_track',
  ACHIEVED = 'achieved',
}

export enum ReportType {
  CSRD = 'csrd',
  CBAM = 'cbam',
  CDP = 'cdp',
  CUSTOM = 'custom',
}

export enum ReportStatus {
  DRAFT = 'draft',
  REVIEW = 'review',
  ASSURANCE = 'assurance',
  FINAL = 'final',
}

// ── Entities ─────────────────────────────────────────────────────────────────

export interface User {
  id: string
  email: string
  name: string
  role: UserRole
  status: 'active' | 'inactive'
  avatarUrl?: string
  supplierId?: string  // set if role is SUPPLIER or SUP_ESG
  createdAt: Date
  updatedAt: Date
}

export interface Supplier {
  id: string
  code: string
  name: string
  nameEn?: string
  tier: SupplierTier
  country: string
  industry: string
  status: SupplierStatus
  riskLevel: RiskLevel
  riskScore?: number
  carbonIntensity?: number  // tCO2e per unit revenue
  contactEmail?: string
  website?: string
  employeeCount?: number
  annualRevenue?: number
  certifications?: string[]
  createdAt: Date
  updatedAt: Date
}

export interface SupplierContact {
  id: string
  supplierId: string
  name: string
  title: string
  email: string
  phone?: string
  isPrimary: boolean
}

export interface SAQ {
  id: string
  supplierId: string
  templateId: string
  period: string  // e.g. "2024-Q1", "2024-FY"
  status: SAQStatus
  score?: number
  sentAt?: Date
  submittedAt?: Date
  dueDate: Date
  createdById: string
  createdAt: Date
  updatedAt: Date
}

export interface SAQQuestion {
  id: string
  templateId: string
  section: string
  question: string
  questionZh: string
  type: 'yes_no' | 'rating' | 'text' | 'file' | 'number'
  required: boolean
  weight: number
  order: number
}

export interface SAQResponse {
  id: string
  saqId: string
  questionId: string
  answer: string | number | boolean
  evidence?: string  // file URL
  comment?: string
}

export interface CAP {
  id: string
  supplierId: string
  saqId?: string
  auditId?: string
  title: string
  status: CAPStatus
  riskLevel: RiskLevel
  dueDate: Date
  assigneeId?: string
  closedAt?: Date
  createdAt: Date
  updatedAt: Date
}

export interface CAPFinding {
  id: string
  capId: string
  category: string
  description: string
  recommendation: string
  evidence?: string
  resolvedAt?: Date
}

export interface TradeGood {
  id: string
  supplierId: string
  name: string
  nameZh?: string
  hsCode: string
  cbamApplicable: boolean
  cbamSector?: string  // 'cement'|'steel'|'aluminium'|'fertiliser'|'electricity'|'hydrogen'
  annualVolume?: number
  unit?: string
  countryOfOrigin: string
  createdAt: Date
  updatedAt: Date
}

export interface PCFRecord {
  id: string
  tradeGoodId?: string
  supplierId: string
  productName: string
  functionalUnit: string
  scope1KgCO2e: number
  scope2KgCO2e: number
  scope3KgCO2e: number
  totalKgCO2e: number
  year: number
  status: PCFStatus
  methodology?: string  // e.g. "GHG Protocol", "ISO 14067"
  verifiedById?: string
  verifiedAt?: Date
  createdAt: Date
  updatedAt: Date
}

export interface DecarbPlan {
  id: string
  supplierId: string
  baselineYear: number
  targetYear: number
  baselineEmissions: number  // tCO2e
  targetReduction: number    // percentage
  sbtiAligned: boolean
  status: DecarbStatus
  milestones?: DecarbMilestone[]
  createdAt: Date
  updatedAt: Date
}

export interface DecarbMilestone {
  id: string
  planId: string
  year: number
  targetEmissions: number
  actualEmissions?: number
  initiative: string
  status: 'planned' | 'in_progress' | 'achieved' | 'missed'
}

export interface Report {
  id: string
  type: ReportType
  title: string
  period: string
  status: ReportStatus
  createdById: string
  assuranceLevel?: 'limited' | 'reasonable'
  publishedAt?: Date
  fileUrl?: string
  createdAt: Date
  updatedAt: Date
}

export interface Notification {
  id: string
  userId: string
  type: 'saq_due' | 'cap_overdue' | 'risk_alert' | 'pcf_verified' | 'report_ready' | 'system'
  title: string
  body: string
  isRead: boolean
  linkTo?: string
  createdAt: string
}

// ── API Response Types ────────────────────────────────────────────────────────

export interface ApiResponse<T> {
  data: T
  message?: string
}

export interface PaginatedResponse<T> {
  data: T[]
  total: number
  page: number
  limit: number
}

export interface ApiError {
  statusCode: number
  message: string
  errors?: Record<string, string[]>
}

// ── Dashboard Summary Types ───────────────────────────────────────────────────

export interface DashboardStats {
  totalSuppliers: number
  activeSuppliers: number
  highRiskSuppliers: number
  saqCompletionRate: number
  openCAPCount: number
  avgCarbonIntensity: number
  cbamExposureCount: number
}

export interface RiskDistribution {
  low: number
  medium: number
  high: number
  critical: number
}

// ── RBAC ─────────────────────────────────────────────────────────────────────

export const ROLE_MODULE_ACCESS: Record<UserRole, string[]> = {
  [UserRole.ADMIN]:    ['dashboard', 'suppliers', 'saq', 'cap', 'tradegoods', 'pcf', 'decarb', 'reports', 'settings', 'portal'],
  [UserRole.BUYER]:    ['dashboard', 'suppliers', 'tradegoods', 'cap'],
  [UserRole.SUSTAIN]:  ['dashboard', 'suppliers', 'saq', 'cap', 'pcf', 'decarb', 'reports'],
  [UserRole.COMPLY]:   ['dashboard', 'suppliers', 'saq', 'cap', 'tradegoods', 'reports'],
  [UserRole.ANALYST]:  ['dashboard', 'suppliers', 'saq', 'pcf', 'decarb', 'reports'],
  [UserRole.SUPPLIER]: ['portal'],
  [UserRole.SUP_ESG]:  ['portal'],
}
