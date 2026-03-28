'use client'

import { useQuery } from '@tanstack/react-query'
import { dashboardApi } from '@/lib/api'
import { useLang } from '@/contexts/LangContext'
import { Users, AlertTriangle, ClipboardCheck, FileWarning, Wind, Package } from 'lucide-react'
import { PieChart, Pie, Cell, Tooltip, ResponsiveContainer, Legend } from 'recharts'

const RISK_COLORS = { low: '#16a34a', medium: '#d97706', high: '#dc2626', critical: '#7c3aed' }

export function DashboardView() {
  const { t } = useLang()

  const { data: statsData } = useQuery({
    queryKey: ['dashboard', 'stats'],
    queryFn: () => dashboardApi.stats().then(r => r.data.data),
  })

  const { data: riskData } = useQuery({
    queryKey: ['dashboard', 'risk-distribution'],
    queryFn: () => dashboardApi.riskDistribution().then(r => r.data.data),
  })

  const stats = statsData ?? {
    totalSuppliers: 0, activeSuppliers: 0, highRiskSuppliers: 0,
    saqCompletionRate: 0, openCAPCount: 0, avgCarbonIntensity: 0, cbamExposureCount: 0
  }

  const riskChartData = riskData ? [
    { name: t('risk.low'), value: riskData.low, color: RISK_COLORS.low },
    { name: t('risk.medium'), value: riskData.medium, color: RISK_COLORS.medium },
    { name: t('risk.high'), value: riskData.high, color: RISK_COLORS.high },
    { name: t('risk.critical'), value: riskData.critical, color: RISK_COLORS.critical },
  ] : []

  const statCards = [
    { icon: Users,          label: t('dashboard.totalSuppliers'),    value: stats.totalSuppliers,                  color: 'text-blue-600',   bg: 'bg-blue-50' },
    { icon: AlertTriangle,  label: t('dashboard.highRisk'),          value: stats.highRiskSuppliers,               color: 'text-red-600',    bg: 'bg-red-50' },
    { icon: ClipboardCheck, label: t('dashboard.saqCompletion'),     value: `${stats.saqCompletionRate}%`,         color: 'text-green-600',  bg: 'bg-green-50' },
    { icon: FileWarning,    label: t('dashboard.openCAPs'),          value: stats.openCAPCount,                    color: 'text-amber-600',  bg: 'bg-amber-50' },
    { icon: Wind,           label: t('dashboard.avgCarbonIntensity'),value: stats.avgCarbonIntensity?.toFixed(2),  color: 'text-teal-600',   bg: 'bg-teal-50' },
    { icon: Package,        label: t('dashboard.cbamExposure'),      value: stats.cbamExposureCount,               color: 'text-purple-600', bg: 'bg-purple-50' },
  ]

  return (
    <div className="space-y-6">
      <h1 className="font-heading text-2xl font-semibold text-text-primary">{t('nav.dashboard')}</h1>

      {/* Stats grid */}
      <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
        {statCards.map((card) => {
          const Icon = card.icon
          return (
            <div key={card.label} className="card p-4">
              <div className={`w-10 h-10 rounded-lg ${card.bg} flex items-center justify-center mb-3`}>
                <Icon size={20} className={card.color} />
              </div>
              <div className="text-2xl font-heading font-bold text-text-primary">{card.value ?? '—'}</div>
              <div className="text-xs text-text-secondary mt-1 leading-tight">{card.label}</div>
            </div>
          )
        })}
      </div>

      {/* Charts row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Risk distribution */}
        <div className="card p-5">
          <h2 className="font-heading font-semibold text-text-primary mb-4">{t('dashboard.riskDistribution')}</h2>
          {riskChartData.length > 0 ? (
            <ResponsiveContainer width="100%" height={240}>
              <PieChart>
                <Pie data={riskChartData} cx="50%" cy="50%" innerRadius={60} outerRadius={90} paddingAngle={3} dataKey="value">
                  {riskChartData.map((entry, idx) => (
                    <Cell key={idx} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip formatter={(value: number) => [`${value} 家`, '']} />
                <Legend />
              </PieChart>
            </ResponsiveContainer>
          ) : (
            <div className="h-60 flex items-center justify-center text-text-secondary text-sm">{t('common.noData')}</div>
          )}
        </div>

        {/* Placeholder for trend chart */}
        <div className="card p-5">
          <h2 className="font-heading font-semibold text-text-primary mb-4">{t('dashboard.carbonTrend')}</h2>
          <div className="h-60 flex items-center justify-center text-text-secondary text-sm">
            {t('common.comingSoon')}
          </div>
        </div>
      </div>
    </div>
  )
}
