'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { saqApi } from '@/lib/api'
import { useLang } from '@/contexts/LangContext'
import { saqStatusClass, formatDate, cn } from '@/lib/utils'
import { SAQStatus } from '@esg-chain/types'
import { Plus, Search, ChevronLeft, ChevronRight, X, Send, XCircle } from 'lucide-react'

interface SAQRow {
  id: string
  supplierId: string
  supplier?: { name: string }
  templateId: string
  template?: { name: string; nameZh: string }
  period: string
  status: SAQStatus
  score?: number
  sentAt?: string
  submittedAt?: string
  dueDate: string
  createdAt: string
}

interface CreateSAQForm {
  supplierId: string
  templateId: string
  period: string
  dueDate: string
}

const PAGE_SIZE = 15

export function SAQView() {
  const { t } = useLang()
  const queryClient = useQueryClient()

  const [search, setSearch] = useState('')
  const [filterStatus, setFilterStatus] = useState('')
  const [page, setPage] = useState(1)
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [confirmSendId, setConfirmSendId] = useState<string | null>(null)
  const [confirmCloseId, setConfirmCloseId] = useState<string | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['saq', { search, filterStatus, page }],
    queryFn: () => saqApi.list({
      search: search || undefined,
      status: filterStatus || undefined,
      page,
      limit: PAGE_SIZE,
    }).then(r => r.data),
  })

  const saqs: SAQRow[] = data?.data ?? []
  const total: number = data?.total ?? 0
  const totalPages = Math.ceil(total / PAGE_SIZE)

  // Stats derived from full data
  const statuses = saqs.map(s => s.status)
  const statsData = {
    total,
    sent: saqs.filter(s => s.status === SAQStatus.SENT).length,
    inProgress: saqs.filter(s => s.status === SAQStatus.IN_PROGRESS).length,
    submitted: saqs.filter(s => s.status === SAQStatus.SUBMITTED).length,
    overdue: saqs.filter(s => new Date(s.dueDate) < new Date() && ![SAQStatus.CLOSED, SAQStatus.REVIEWED].includes(s.status)).length,
  }

  const { register, handleSubmit, reset, formState: { errors } } = useForm<CreateSAQForm>()

  const createMutation = useMutation({
    mutationFn: (data: CreateSAQForm) => saqApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['saq'] })
      setShowCreateModal(false)
      reset()
    },
  })

  const sendMutation = useMutation({
    mutationFn: (id: string) => saqApi.send(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['saq'] })
      setConfirmSendId(null)
    },
  })

  const closeMutation = useMutation({
    mutationFn: (id: string) => saqApi.update(id, { status: 'closed' }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['saq'] })
      setConfirmCloseId(null)
    },
  })

  const statusOptions = [
    { value: '', label: t('common.all') },
    { value: SAQStatus.DRAFT, label: t('saqStatus.draft') },
    { value: SAQStatus.SENT, label: t('saqStatus.sent') },
    { value: SAQStatus.IN_PROGRESS, label: t('saqStatus.in_progress') },
    { value: SAQStatus.SUBMITTED, label: t('saqStatus.submitted') },
    { value: SAQStatus.REVIEWED, label: t('saqStatus.reviewed') },
    { value: SAQStatus.CLOSED, label: t('saqStatus.closed') },
  ]

  const statCards = [
    { label: t('saq.total'), value: total, color: 'text-blue-600', bg: 'bg-blue-50' },
    { label: t('saq.sent'), value: statsData.sent, color: 'text-teal-600', bg: 'bg-teal-50' },
    { label: t('saq.inProgress'), value: statsData.inProgress, color: 'text-amber-600', bg: 'bg-amber-50' },
    { label: t('saq.submitted'), value: statsData.submitted, color: 'text-green-600', bg: 'bg-green-50' },
    { label: t('saq.overdue'), value: statsData.overdue, color: 'text-red-600', bg: 'bg-red-50' },
  ]

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="font-heading text-2xl font-semibold text-text-primary">{t('saq.title')}</h1>
        <button onClick={() => setShowCreateModal(true)} className="btn-primary flex items-center gap-2">
          <Plus size={16} />
          {t('saq.new')}
        </button>
      </div>

      {/* Stat cards */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        {statCards.map(card => (
          <div key={card.label} className="card p-4">
            <div className={`text-2xl font-heading font-bold ${card.color}`}>{card.value}</div>
            <div className="text-xs text-text-secondary mt-1">{card.label}</div>
          </div>
        ))}
      </div>

      {/* Filters */}
      <div className="card p-4 flex flex-wrap gap-3 items-center">
        <div className="relative flex-1 min-w-48">
          <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" />
          <input
            className="input pl-9"
            placeholder={t('saq.searchPlaceholder')}
            value={search}
            onChange={e => { setSearch(e.target.value); setPage(1) }}
          />
        </div>
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
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('saq.supplier')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('saq.template')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('saq.period')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('saq.status')}</th>
                <th className="text-right px-4 py-3 text-xs font-medium text-text-secondary">{t('saq.score')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('saq.dueDate')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('saq.sentAt')}</th>
                <th className="text-center px-4 py-3 text-xs font-medium text-text-secondary">{t('common.actions')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {isLoading ? (
                <tr><td colSpan={8} className="text-center py-12 text-text-secondary">{t('common.loading')}</td></tr>
              ) : saqs.length === 0 ? (
                <tr><td colSpan={8} className="text-center py-12 text-text-secondary">{t('common.noData')}</td></tr>
              ) : (
                saqs.map(saq => (
                  <tr key={saq.id} className="hover:bg-surface-2 transition-colors">
                    <td className="px-4 py-3 font-medium text-text-primary">{saq.supplier?.name ?? saq.supplierId}</td>
                    <td className="px-4 py-3 text-text-secondary text-xs">{saq.template?.nameZh ?? saq.templateId}</td>
                    <td className="px-4 py-3">
                      <span className="font-mono text-xs bg-surface-2 px-2 py-0.5 rounded">{saq.period}</span>
                    </td>
                    <td className="px-4 py-3">
                      <span className={saqStatusClass(saq.status)}>{t(`saqStatus.${saq.status}`)}</span>
                    </td>
                    <td className="px-4 py-3 text-right font-mono text-xs">
                      {saq.score != null ? `${saq.score.toFixed(1)}` : '—'}
                    </td>
                    <td className={cn('px-4 py-3 text-xs', new Date(saq.dueDate) < new Date() && saq.status !== SAQStatus.CLOSED ? 'text-red-600 font-medium' : 'text-text-secondary')}>
                      {formatDate(saq.dueDate)}
                    </td>
                    <td className="px-4 py-3 text-text-secondary text-xs">{saq.sentAt ? formatDate(saq.sentAt) : '—'}</td>
                    <td className="px-4 py-3">
                      <div className="flex items-center justify-center gap-1">
                        {saq.status === SAQStatus.DRAFT && (
                          <button
                            onClick={() => setConfirmSendId(saq.id)}
                            className="flex items-center gap-1 text-xs px-2 py-1 rounded bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors"
                          >
                            <Send size={12} />
                            {t('saq.send')}
                          </button>
                        )}
                        {![SAQStatus.CLOSED, SAQStatus.REVIEWED].includes(saq.status) && (
                          <button
                            onClick={() => setConfirmCloseId(saq.id)}
                            className="flex items-center gap-1 text-xs px-2 py-1 rounded bg-stone-50 text-stone-600 hover:bg-stone-100 transition-colors"
                          >
                            <XCircle size={12} />
                            {t('saq.close')}
                          </button>
                        )}
                      </div>
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
            <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page <= 1} className="p-1.5 rounded hover:bg-surface-2 disabled:opacity-40">
              <ChevronLeft size={16} />
            </button>
            <span className="text-xs text-text-secondary">{page} / {totalPages || 1}</span>
            <button onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={page >= totalPages} className="p-1.5 rounded hover:bg-surface-2 disabled:opacity-40">
              <ChevronRight size={16} />
            </button>
          </div>
        </div>
      </div>

      {/* Create Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
          <div className="bg-surface rounded-card shadow-modal w-full max-w-md">
            <div className="flex items-center justify-between px-6 py-4 border-b border-border">
              <h2 className="font-heading font-semibold text-text-primary">{t('saq.new')}</h2>
              <button onClick={() => { setShowCreateModal(false); reset() }} className="p-1.5 rounded hover:bg-surface-2"><X size={18} /></button>
            </div>
            <form onSubmit={handleSubmit(d => createMutation.mutate(d))} className="px-6 py-5 space-y-4">
              <div>
                <label className="label">{t('saq.supplier')} <span className="text-red-500">*</span></label>
                <input className={cn('input', errors.supplierId && 'border-red-400')} {...register('supplierId', { required: true })} placeholder="供應商 ID" />
              </div>
              <div>
                <label className="label">{t('saq.template')} <span className="text-red-500">*</span></label>
                <input className={cn('input', errors.templateId && 'border-red-400')} {...register('templateId', { required: true })} placeholder="問卷範本 ID" />
              </div>
              <div>
                <label className="label">{t('saq.period')} <span className="text-red-500">*</span></label>
                <input className={cn('input', errors.period && 'border-red-400')} {...register('period', { required: true })} placeholder="2024-FY" />
              </div>
              <div>
                <label className="label">{t('saq.dueDate')} <span className="text-red-500">*</span></label>
                <input type="date" className={cn('input', errors.dueDate && 'border-red-400')} {...register('dueDate', { required: true })} />
              </div>
              <div className="flex justify-end gap-3 pt-2">
                <button type="button" onClick={() => { setShowCreateModal(false); reset() }} className="btn-secondary">{t('common.cancel')}</button>
                <button type="submit" className="btn-primary" disabled={createMutation.isPending}>{createMutation.isPending ? t('common.loading') : t('common.save')}</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Send Confirm Dialog */}
      {confirmSendId && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
          <div className="bg-surface rounded-card shadow-modal w-full max-w-sm p-6 space-y-4">
            <h3 className="font-heading font-semibold text-text-primary">{t('saq.send')}</h3>
            <p className="text-sm text-text-secondary">{t('saq.sendConfirm')}</p>
            <div className="flex justify-end gap-3">
              <button onClick={() => setConfirmSendId(null)} className="btn-secondary">{t('common.cancel')}</button>
              <button onClick={() => sendMutation.mutate(confirmSendId!)} className="btn-primary" disabled={sendMutation.isPending}>
                {sendMutation.isPending ? t('common.loading') : t('common.confirm')}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Close Confirm Dialog */}
      {confirmCloseId && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
          <div className="bg-surface rounded-card shadow-modal w-full max-w-sm p-6 space-y-4">
            <h3 className="font-heading font-semibold text-text-primary">{t('saq.close')}</h3>
            <p className="text-sm text-text-secondary">{t('saq.closeConfirm')}</p>
            <div className="flex justify-end gap-3">
              <button onClick={() => setConfirmCloseId(null)} className="btn-secondary">{t('common.cancel')}</button>
              <button onClick={() => closeMutation.mutate(confirmCloseId!)} className="btn-primary" disabled={closeMutation.isPending}>
                {closeMutation.isPending ? t('common.loading') : t('common.confirm')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
