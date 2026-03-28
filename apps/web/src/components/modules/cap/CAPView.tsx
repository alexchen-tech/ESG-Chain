'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { capApi } from '@/lib/api'
import { useLang } from '@/contexts/LangContext'
import { capStatusClass, riskBadgeClass, formatDate, cn } from '@/lib/utils'
import { CAPStatus, RiskLevel } from '@esg-chain/types'
import { Plus, Search, ChevronLeft, ChevronRight, X, CheckCircle } from 'lucide-react'

interface CAPRow {
  id: string
  supplierId: string
  supplier?: { name: string }
  title: string
  status: CAPStatus
  riskLevel: RiskLevel
  dueDate: string
  assigneeId?: string
  closedAt?: string
  createdAt: string
}

interface CreateCAPForm {
  supplierId: string
  title: string
  riskLevel: RiskLevel
  dueDate: string
  assigneeId: string
}

const PAGE_SIZE = 15

export function CAPView() {
  const { t } = useLang()
  const queryClient = useQueryClient()

  const [search, setSearch] = useState('')
  const [filterStatus, setFilterStatus] = useState('')
  const [filterRisk, setFilterRisk] = useState('')
  const [page, setPage] = useState(1)
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [confirmCloseId, setConfirmCloseId] = useState<string | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['cap', { search, filterStatus, filterRisk, page }],
    queryFn: () => capApi.list({
      search: search || undefined,
      status: filterStatus || undefined,
      riskLevel: filterRisk || undefined,
      page,
      limit: PAGE_SIZE,
    }).then(r => r.data),
  })

  const caps: CAPRow[] = data?.data ?? []
  const total: number = data?.total ?? 0
  const totalPages = Math.ceil(total / PAGE_SIZE)

  const open = caps.filter(c => c.status === CAPStatus.OPEN).length
  const inProgress = caps.filter(c => c.status === CAPStatus.IN_PROGRESS).length
  const overdue = caps.filter(c => c.status === CAPStatus.OVERDUE).length
  const closed = caps.filter(c => c.status === CAPStatus.CLOSED).length

  const { register, handleSubmit, reset, formState: { errors } } = useForm<CreateCAPForm>()

  const createMutation = useMutation({
    mutationFn: (data: CreateCAPForm) => capApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['cap'] })
      setShowCreateModal(false)
      reset()
    },
  })

  const closeMutation = useMutation({
    mutationFn: (id: string) => capApi.close(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['cap'] })
      setConfirmCloseId(null)
    },
  })

  const statusOptions = [
    { value: '', label: t('common.all') },
    { value: CAPStatus.OPEN, label: t('capStatus.open') },
    { value: CAPStatus.IN_PROGRESS, label: t('capStatus.in_progress') },
    { value: CAPStatus.VERIFIED, label: t('capStatus.verified') },
    { value: CAPStatus.CLOSED, label: t('capStatus.closed') },
    { value: CAPStatus.OVERDUE, label: t('capStatus.overdue') },
  ]

  const riskOptions = [
    { value: '', label: t('common.all') },
    { value: RiskLevel.LOW, label: t('risk.low') },
    { value: RiskLevel.MEDIUM, label: t('risk.medium') },
    { value: RiskLevel.HIGH, label: t('risk.high') },
    { value: RiskLevel.CRITICAL, label: t('risk.critical') },
  ]

  const statCards = [
    { label: t('cap.open'), value: open, color: 'text-red-600', bg: 'bg-red-50' },
    { label: t('cap.inProgress'), value: inProgress, color: 'text-amber-600', bg: 'bg-amber-50' },
    { label: t('cap.overdue'), value: overdue, color: 'text-purple-600', bg: 'bg-purple-50' },
    { label: t('cap.closed'), value: closed, color: 'text-green-600', bg: 'bg-green-50' },
  ]

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="font-heading text-2xl font-semibold text-text-primary">{t('cap.title')}</h1>
        <button onClick={() => setShowCreateModal(true)} className="btn-primary flex items-center gap-2">
          <Plus size={16} />
          {t('cap.new')}
        </button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
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
            placeholder={t('cap.searchPlaceholder')}
            value={search}
            onChange={e => { setSearch(e.target.value); setPage(1) }}
          />
        </div>
        <select className="input w-40" value={filterStatus} onChange={e => { setFilterStatus(e.target.value); setPage(1) }}>
          {statusOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select className="input w-36" value={filterRisk} onChange={e => { setFilterRisk(e.target.value); setPage(1) }}>
          {riskOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {/* Table */}
      <div className="card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-surface-2 border-b border-border">
              <tr>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('cap.capTitle')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('cap.supplier')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('cap.riskLevel')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('cap.status')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('cap.dueDate')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('common.createdAt')}</th>
                <th className="text-center px-4 py-3 text-xs font-medium text-text-secondary">{t('common.actions')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {isLoading ? (
                <tr><td colSpan={7} className="text-center py-12 text-text-secondary">{t('common.loading')}</td></tr>
              ) : caps.length === 0 ? (
                <tr><td colSpan={7} className="text-center py-12 text-text-secondary">{t('common.noData')}</td></tr>
              ) : (
                caps.map(cap => (
                  <tr key={cap.id} className="hover:bg-surface-2 transition-colors">
                    <td className="px-4 py-3 font-medium text-text-primary">{cap.title}</td>
                    <td className="px-4 py-3 text-text-secondary">{cap.supplier?.name ?? cap.supplierId}</td>
                    <td className="px-4 py-3">
                      <span className={riskBadgeClass(cap.riskLevel)}>{t(`risk.${cap.riskLevel}`)}</span>
                    </td>
                    <td className="px-4 py-3">
                      <span className={capStatusClass(cap.status)}>{t(`capStatus.${cap.status}`)}</span>
                    </td>
                    <td className={cn('px-4 py-3 text-xs', new Date(cap.dueDate) < new Date() && cap.status !== CAPStatus.CLOSED ? 'text-red-600 font-medium' : 'text-text-secondary')}>
                      {formatDate(cap.dueDate)}
                    </td>
                    <td className="px-4 py-3 text-text-secondary text-xs">{formatDate(cap.createdAt)}</td>
                    <td className="px-4 py-3 text-center">
                      {![CAPStatus.CLOSED, CAPStatus.VERIFIED].includes(cap.status) && (
                        <button
                          onClick={() => setConfirmCloseId(cap.id)}
                          className="flex items-center gap-1 text-xs px-2 py-1 rounded bg-green-50 text-green-700 hover:bg-green-100 transition-colors mx-auto"
                        >
                          <CheckCircle size={12} />
                          {t('cap.close')}
                        </button>
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
              <h2 className="font-heading font-semibold text-text-primary">{t('cap.new')}</h2>
              <button onClick={() => { setShowCreateModal(false); reset() }} className="p-1.5 rounded hover:bg-surface-2"><X size={18} /></button>
            </div>
            <form onSubmit={handleSubmit(d => createMutation.mutate(d))} className="px-6 py-5 space-y-4">
              <div>
                <label className="label">{t('cap.supplier')} <span className="text-red-500">*</span></label>
                <input className={cn('input', errors.supplierId && 'border-red-400')} {...register('supplierId', { required: true })} placeholder="供應商 ID" />
              </div>
              <div>
                <label className="label">{t('cap.capTitle')} <span className="text-red-500">*</span></label>
                <input className={cn('input', errors.title && 'border-red-400')} {...register('title', { required: true })} placeholder="矯正行動標題" />
              </div>
              <div>
                <label className="label">{t('cap.riskLevel')} <span className="text-red-500">*</span></label>
                <select className="input" {...register('riskLevel', { required: true })}>
                  {[RiskLevel.LOW, RiskLevel.MEDIUM, RiskLevel.HIGH, RiskLevel.CRITICAL].map(r => (
                    <option key={r} value={r}>{t(`risk.${r}`)}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="label">{t('cap.dueDate')} <span className="text-red-500">*</span></label>
                <input type="date" className={cn('input', errors.dueDate && 'border-red-400')} {...register('dueDate', { required: true })} />
              </div>
              <div>
                <label className="label">{t('cap.assignee')}</label>
                <input className="input" {...register('assigneeId')} placeholder="負責人 ID" />
              </div>
              <div className="flex justify-end gap-3 pt-2">
                <button type="button" onClick={() => { setShowCreateModal(false); reset() }} className="btn-secondary">{t('common.cancel')}</button>
                <button type="submit" className="btn-primary" disabled={createMutation.isPending}>{createMutation.isPending ? t('common.loading') : t('common.save')}</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Close Confirm */}
      {confirmCloseId && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
          <div className="bg-surface rounded-card shadow-modal w-full max-w-sm p-6 space-y-4">
            <h3 className="font-heading font-semibold text-text-primary">{t('cap.close')}</h3>
            <p className="text-sm text-text-secondary">{t('cap.closeConfirm')}</p>
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
