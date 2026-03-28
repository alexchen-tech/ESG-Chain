'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { reportsApi } from '@/lib/api'
import { useLang } from '@/contexts/LangContext'
import { formatDate, cn } from '@/lib/utils'
import { ReportType, ReportStatus } from '@esg-chain/types'
import { Plus, Search, ChevronLeft, ChevronRight, X, Download } from 'lucide-react'

interface ReportRow {
  id: string
  type: ReportType
  title: string
  period: string
  status: ReportStatus
  createdById: string
  createdBy?: { name: string }
  assuranceLevel?: string
  publishedAt?: string
  fileUrl?: string
  createdAt: string
}

interface CreateReportForm {
  type: ReportType
  title: string
  period: string
  assuranceLevel: string
}

const PAGE_SIZE = 15

function reportTypeBadge(type: ReportType) {
  const map: Record<ReportType, string> = {
    [ReportType.CSRD]: 'bg-blue-100 text-blue-700',
    [ReportType.CBAM]: 'bg-amber-100 text-amber-700',
    [ReportType.CDP]: 'bg-teal-100 text-teal-700',
    [ReportType.CUSTOM]: 'bg-stone-100 text-stone-600',
  }
  return `text-xs font-medium font-mono px-2 py-0.5 rounded-full ${map[type] ?? ''}`
}

function reportStatusBadge(status: ReportStatus) {
  const map: Record<ReportStatus, string> = {
    [ReportStatus.DRAFT]: 'bg-stone-100 text-stone-500',
    [ReportStatus.REVIEW]: 'bg-blue-100 text-blue-700',
    [ReportStatus.ASSURANCE]: 'bg-amber-100 text-amber-700',
    [ReportStatus.FINAL]: 'bg-green-100 text-green-700',
  }
  return `text-xs font-medium px-2 py-0.5 rounded-full ${map[status] ?? ''}`
}

export function ReportsView() {
  const { t } = useLang()
  const queryClient = useQueryClient()

  const [search, setSearch] = useState('')
  const [filterType, setFilterType] = useState('')
  const [filterStatus, setFilterStatus] = useState('')
  const [page, setPage] = useState(1)
  const [showCreateModal, setShowCreateModal] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['reports', { search, filterType, filterStatus, page }],
    queryFn: () => reportsApi.list({
      search: search || undefined,
      type: filterType || undefined,
      status: filterStatus || undefined,
      page,
      limit: PAGE_SIZE,
    }).then(r => r.data),
  })

  const reports: ReportRow[] = data?.data ?? []
  const total: number = data?.total ?? 0
  const totalPages = Math.ceil(total / PAGE_SIZE)

  const { register, handleSubmit, reset, formState: { errors } } = useForm<CreateReportForm>({
    defaultValues: { type: ReportType.CSRD }
  })

  const createMutation = useMutation({
    mutationFn: (data: CreateReportForm) => reportsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['reports'] })
      setShowCreateModal(false)
      reset()
    },
  })

  const typeOptions = [
    { value: '', label: t('common.all') },
    { value: ReportType.CSRD, label: t('reportType.csrd') },
    { value: ReportType.CBAM, label: t('reportType.cbam') },
    { value: ReportType.CDP, label: t('reportType.cdp') },
    { value: ReportType.CUSTOM, label: t('reportType.custom') },
  ]

  const statusOptions = [
    { value: '', label: t('common.all') },
    { value: ReportStatus.DRAFT, label: t('reportStatus.draft') },
    { value: ReportStatus.REVIEW, label: t('reportStatus.review') },
    { value: ReportStatus.ASSURANCE, label: t('reportStatus.assurance') },
    { value: ReportStatus.FINAL, label: t('reportStatus.final') },
  ]

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="font-heading text-2xl font-semibold text-text-primary">{t('reports.title')}</h1>
        <button onClick={() => setShowCreateModal(true)} className="btn-primary flex items-center gap-2">
          <Plus size={16} />
          {t('reports.new')}
        </button>
      </div>

      {/* Filters */}
      <div className="card p-4 flex flex-wrap gap-3 items-center">
        <div className="relative flex-1 min-w-48">
          <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" />
          <input
            className="input pl-9"
            placeholder={t('reports.searchPlaceholder')}
            value={search}
            onChange={e => { setSearch(e.target.value); setPage(1) }}
          />
        </div>
        <select className="input w-36" value={filterType} onChange={e => { setFilterType(e.target.value); setPage(1) }}>
          {typeOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select className="input w-40" value={filterStatus} onChange={e => { setFilterStatus(e.target.value); setPage(1) }}>
          {statusOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {/* Table */}
      <div className="card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-surface-2 border-b border-border">
              <tr>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('reports.reportTitle')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('reports.type')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('reports.period')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('reports.status')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('reports.assuranceLevel')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('reports.createdBy')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('reports.publishedAt')}</th>
                <th className="text-center px-4 py-3 text-xs font-medium text-text-secondary">{t('common.actions')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {isLoading ? (
                <tr><td colSpan={8} className="text-center py-12 text-text-secondary">{t('common.loading')}</td></tr>
              ) : reports.length === 0 ? (
                <tr><td colSpan={8} className="text-center py-12 text-text-secondary">{t('common.noData')}</td></tr>
              ) : (
                reports.map(report => (
                  <tr key={report.id} className="hover:bg-surface-2 transition-colors">
                    <td className="px-4 py-3 font-medium text-text-primary">{report.title}</td>
                    <td className="px-4 py-3">
                      <span className={reportTypeBadge(report.type)}>{t(`reportType.${report.type}`)}</span>
                    </td>
                    <td className="px-4 py-3">
                      <span className="font-mono text-xs bg-surface-2 px-2 py-0.5 rounded">{report.period}</span>
                    </td>
                    <td className="px-4 py-3">
                      <span className={reportStatusBadge(report.status)}>{t(`reportStatus.${report.status}`)}</span>
                    </td>
                    <td className="px-4 py-3 text-text-secondary text-xs">
                      {report.assuranceLevel ? t(`assurance.${report.assuranceLevel}`) : '—'}
                    </td>
                    <td className="px-4 py-3 text-text-secondary">{report.createdBy?.name ?? report.createdById}</td>
                    <td className="px-4 py-3 text-text-secondary text-xs">{report.publishedAt ? formatDate(report.publishedAt) : '—'}</td>
                    <td className="px-4 py-3 text-center">
                      {report.fileUrl && report.status === ReportStatus.FINAL && (
                        <a
                          href={report.fileUrl}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="flex items-center gap-1 text-xs px-2 py-1 rounded bg-accent-light text-accent hover:bg-accent hover:text-white transition-colors mx-auto w-fit"
                        >
                          <Download size={12} />
                          {t('reports.download')}
                        </a>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        <div className="flex items-center justify-between px-4 py-3 border-t border-border">
          <span className="text-xs text-text-secondary">{t('common.total')} {total} {t('common.items')}</span>
          <div className="flex items-center gap-2">
            <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page <= 1} className="p-1.5 rounded hover:bg-surface-2 disabled:opacity-40"><ChevronLeft size={16} /></button>
            <span className="text-xs text-text-secondary">{page} / {totalPages || 1}</span>
            <button onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={page >= totalPages} className="p-1.5 rounded hover:bg-surface-2 disabled:opacity-40"><ChevronRight size={16} /></button>
          </div>
        </div>
      </div>

      {/* Create Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
          <div className="bg-surface rounded-card shadow-modal w-full max-w-md">
            <div className="flex items-center justify-between px-6 py-4 border-b border-border">
              <h2 className="font-heading font-semibold text-text-primary">{t('reports.new')}</h2>
              <button onClick={() => { setShowCreateModal(false); reset() }} className="p-1.5 rounded hover:bg-surface-2"><X size={18} /></button>
            </div>
            <form onSubmit={handleSubmit(d => createMutation.mutate(d))} className="px-6 py-5 space-y-4">
              <div>
                <label className="label">{t('reports.reportTitle')} <span className="text-red-500">*</span></label>
                <input className={cn('input', errors.title && 'border-red-400')} {...register('title', { required: true })} placeholder="2024 CSRD 永續報告" />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label">{t('reports.type')} <span className="text-red-500">*</span></label>
                  <select className="input" {...register('type', { required: true })}>
                    {[ReportType.CSRD, ReportType.CBAM, ReportType.CDP, ReportType.CUSTOM].map(t_ => (
                      <option key={t_} value={t_}>{t(`reportType.${t_}`)}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="label">{t('reports.period')} <span className="text-red-500">*</span></label>
                  <input className={cn('input', errors.period && 'border-red-400')} {...register('period', { required: true })} placeholder="2024-FY" />
                </div>
              </div>
              <div>
                <label className="label">{t('reports.assuranceLevel')}</label>
                <select className="input" {...register('assuranceLevel')}>
                  <option value="">— {t('common.optional')} —</option>
                  <option value="limited">{t('assurance.limited')}</option>
                  <option value="reasonable">{t('assurance.reasonable')}</option>
                </select>
              </div>
              <div className="flex justify-end gap-3 pt-2">
                <button type="button" onClick={() => { setShowCreateModal(false); reset() }} className="btn-secondary">{t('common.cancel')}</button>
                <button type="submit" className="btn-primary" disabled={createMutation.isPending}>{createMutation.isPending ? t('common.loading') : t('common.save')}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
