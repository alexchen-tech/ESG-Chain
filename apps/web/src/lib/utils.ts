import { type ClassValue, clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'
import { RiskLevel, SupplierStatus, SAQStatus, CAPStatus } from '@esg-chain/types'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatDate(date: Date | string | undefined): string {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('zh-TW', {
    year: 'numeric', month: '2-digit', day: '2-digit'
  })
}

export function formatNumber(n: number | undefined, decimals = 0): string {
  if (n === undefined || n === null) return '—'
  return n.toLocaleString('zh-TW', { maximumFractionDigits: decimals })
}

export function riskBadgeClass(level: RiskLevel): string {
  const map: Record<RiskLevel, string> = {
    [RiskLevel.LOW]: 'badge-risk-low',
    [RiskLevel.MEDIUM]: 'badge-risk-medium',
    [RiskLevel.HIGH]: 'badge-risk-high',
    [RiskLevel.CRITICAL]: 'badge-risk-critical',
  }
  return map[level] ?? ''
}

export function supplierStatusClass(status: SupplierStatus): string {
  const map: Record<SupplierStatus, string> = {
    [SupplierStatus.ACTIVE]: 'bg-green-100 text-green-700',
    [SupplierStatus.INACTIVE]: 'bg-stone-100 text-stone-500',
    [SupplierStatus.PENDING]: 'bg-amber-100 text-amber-700',
    [SupplierStatus.SUSPENDED]: 'bg-red-100 text-red-700',
  }
  return `text-xs font-medium px-2 py-0.5 rounded-full ${map[status] ?? ''}`
}

export function saqStatusClass(status: SAQStatus): string {
  const map: Record<SAQStatus, string> = {
    [SAQStatus.DRAFT]: 'bg-stone-100 text-stone-500',
    [SAQStatus.SENT]: 'bg-blue-100 text-blue-700',
    [SAQStatus.IN_PROGRESS]: 'bg-amber-100 text-amber-700',
    [SAQStatus.SUBMITTED]: 'bg-teal-100 text-teal-700',
    [SAQStatus.REVIEWED]: 'bg-green-100 text-green-700',
    [SAQStatus.CLOSED]: 'bg-stone-100 text-stone-400',
  }
  return `text-xs font-medium px-2 py-0.5 rounded-full ${map[status] ?? ''}`
}

export function capStatusClass(status: CAPStatus): string {
  const map: Record<CAPStatus, string> = {
    [CAPStatus.OPEN]: 'bg-red-100 text-red-700',
    [CAPStatus.IN_PROGRESS]: 'bg-amber-100 text-amber-700',
    [CAPStatus.VERIFIED]: 'bg-teal-100 text-teal-700',
    [CAPStatus.CLOSED]: 'bg-green-100 text-green-700',
    [CAPStatus.OVERDUE]: 'bg-purple-100 text-purple-700',
  }
  return `text-xs font-medium px-2 py-0.5 rounded-full ${map[status] ?? ''}`
}
