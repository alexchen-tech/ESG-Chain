'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { decarbApi } from '@/lib/api'
import { useLang } from '@/contexts/LangContext'
import { formatDate, cn } from '@/lib/utils'
import { DecarbStatus } from '@esg-chain/types'
import { Plus, Search, ChevronLeft, ChevronRight, X, CheckCircle2 } from 'lucide-react'

interface DecarbRow {
  id: string
  supplierId: string
  supplier?: { name: string }
  baselineYear: number
  targetYear: number
  baselineEmissions: number
  targetReduction: number
  sbtiAligned: boolean
  status: DecarbStatus
  createdAt: string
}

interface CreateDecarbForm {
  supplierId: string
  baselineYear: string
  targetYear: string
  baselineEmissions: string
  targetReduction: string
  sbtiAligned: boolean
}

const PAGE_SIZE = 15

function decarbStatusBadge(status: DecarbStatus, label: string) {
  const map: Record<DecarbStatus, string> = {
    [DecarbStatus.BASELINE]: 'bg-stone-100 text-stone-500',
    [DecarbStatus.PLANNING]: 'bg-blue-100 text-blue-700',
    [DecarbStatus.COMMITTED]: 'bg-teal-100 text-teal-700',
    [DecarbStatus.ON_TRACK]: 'bg-green-100 text-green-700',
    [DecarbStatus.OFF_TRACK]: 'bg-red-100 text-red-700',
    [DecarbStatus.ACHIEVED]: 'bg-purple-100 text-purple-700',
  }
  return <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${map[status] ?? ''}`}>{label}</span>
}

export function DecarbView() {
  const { t } = useLang()
  const queryClient = useQueryClient()

  const [search, setSearch] = useState('')
  const [filterStatus, setFilterStatus] = useState('')
  const [page, setPage] = useState(1)
  const [showCreateModal, setShowCreateModal] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['decarb', { search, filterStatus, page }],
    queryFn: () => decarbApi.list({
      search: search || undefined,
      status: filterStatus || undefined,
      page,
      limit: PAGE_SIZE,
    }).then(r => r.data),
  })

  const plans: DecarbRow[] = data?.data ?? []
  const total: number = data?.total ?? 0
  const totalPages = Math.ceil(total / PAGE_SIZE)
  const sbtiCount = plans.filter(p => p.sbtiAligned).length
  const onTrackCount = plans.filter(p => p.status === DecarbStatus.ON_TRACK).length

  const { register, handleSubmit, reset, formState: { errors } } = useForm<CreateDecarbForm>()

  const createMutation = useMutation({
    mutationFn: (data: CreateDecarbForm) => decarbApi.create({
      ...data,
      baselineYear: Number(data.baselineYear),
      targetYear: Number(data.targetYear),
      baselineEmissions: Number(data.baselineEmissions),
      targetReduction: Number(data.targetReduction),
    }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['decarb'] })
      setShowCreateModal(false)
      reset()
    },
  })

  const statusOptions = [
    { value: '', label: t('common.all') },
    ...Object.values(DecarbStatus).map(s => ({ value: s, label: t(`decarbStatus.${s}`) }))
  ]

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="font-heading text-2xl font-semibold text-text-primary">{t('decarb.title')}</h1>
        <button onClick={() => setShowCreateModal(true)} className="btn-primary flex items-center gap-2">
          <Plus size={16} />
          {t('decarb.new')}
        </button>
      </div>

      {/* Summary */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="card p-4">
          <div className="text-2xl font-heading font-bold text-blue-600">{total}</div>
          <div className="text-xs text-text-secondary mt-1">計畫總數</div>
        </div>
        <div className="card p-4">
          <div className="text-2xl font-heading font-bold text-teal-600">{sbtiCount}</div>
          <div className="text-xs text-text-secondary mt-1">{t('decarb.sbtiCount')}</div>
        </div>
        <div className="card p-4">
          <div className="text-2xl font-heading font-bold text-green-600">{onTrackCount}</div>
          <div className="text-xs text-text-secondary mt-1">{t('decarb.onTrackCount')}</div>
        </div>
        <div className="card p-4">
          <div className="text-2xl font-heading font-bold text-purple-600">
            {plans.filter(p => p.status === DecarbStatus.ACHIEVED).length}
          </div>
          <div className="text-xs text-text-secondary mt-1">{t('decarbStatus.achieved')}</div>
        </div>
      </div>

      {/* Filters */}
      <div className="card p-4 flex flex-wrap gap-3 items-center">
        <div className="relative flex-1 min-w-48">
          <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" />
          <input
            className="input pl-9"
            placeholder={t('decarb.searchPlaceholder')}
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
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('decarb.supplier')}</th>
                <th className="text-right px-4 py-3 text-xs font-medium text-text-secondary">{t('decarb.baselineYear')}</th>
                <th className="text-right px-4 py-3 text-xs font-medium text-text-secondary">{t('decarb.targetYear')}</th>
                <th className="text-right px-4 py-3 text-xs font-medium text-text-secondary">{t('decarb.baselineEmissions')}</th>
                <th className="text-right px-4 py-3 text-xs font-medium text-text-secondary">{t('decarb.targetReduction')}</th>
                <th className="text-center px-4 py-3 text-xs font-medium text-text-secondary">{t('decarb.sbtiAligned')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('decarb.status')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('common.createdAt')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {isLoading ? (
                <tr><td colSpan={8} className="text-center py-12 text-text-secondary">{t('common.loading')}</td></tr>
              ) : plans.length === 0 ? (
                <tr><td colSpan={8} className="text-center py-12 text-text-secondary">{t('common.noData')}</td></tr>
              ) : (
                plans.map(plan => (
                  <tr key={plan.id} className="hover:bg-surface-2 transition-colors">
                    <td className="px-4 py-3 font-medium text-text-primary">{plan.supplier?.name ?? plan.supplierId}</td>
                    <td className="px-4 py-3 text-right font-mono text-sm">{plan.baselineYear}</td>
                    <td className="px-4 py-3 text-right font-mono text-sm">{plan.targetYear}</td>
                    <td className="px-4 py-3 text-right font-mono text-xs">{plan.baselineEmissions.toLocaleString()} tCO2e</td>
                    <td className="px-4 py-3 text-right font-mono text-sm font-semibold text-accent">{plan.targetReduction}%</td>
                    <td className="px-4 py-3 text-center">
                      {plan.sbtiAligned ? (
                        <CheckCircle2 size={16} className="text-green-600 mx-auto" />
                      ) : (
                        <span className="text-text-secondary text-xs">—</span>
                      )}
                    </td>
                    <td className="px-4 py-3">{decarbStatusBadge(plan.status, t(`decarbStatus.${plan.status}`))}</td>
                    <td className="px-4 py-3 text-text-secondary text-xs">{formatDate(plan.createdAt)}</td>
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
              <h2 className="font-heading font-semibold text-text-primary">{t('decarb.new')}</h2>
              <button onClick={() => { setShowCreateModal(false); reset() }} className="p-1.5 rounded hover:bg-surface-2"><X size={18} /></button>
            </div>
            <form onSubmit={handleSubmit(d => createMutation.mutate(d))} className="px-6 py-5 space-y-4">
              <div>
                <label className="label">{t('decarb.supplier')} <span className="text-red-500">*</span></label>
                <input className={cn('input', errors.supplierId && 'border-red-400')} {...register('supplierId', { required: true })} />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label">{t('decarb.baselineYear')} <span className="text-red-500">*</span></label>
                  <input type="number" className={cn('input', errors.baselineYear && 'border-red-400')} {...register('baselineYear', { required: true })} defaultValue={2020} />
                </div>
                <div>
                  <label className="label">{t('decarb.targetYear')} <span className="text-red-500">*</span></label>
                  <input type="number" className={cn('input', errors.targetYear && 'border-red-400')} {...register('targetYear', { required: true })} defaultValue={2030} />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label">{t('decarb.baselineEmissions')} <span className="text-red-500">*</span></label>
                  <input type="number" step="0.1" className={cn('input', errors.baselineEmissions && 'border-red-400')} {...register('baselineEmissions', { required: true })} />
                </div>
                <div>
                  <label className="label">{t('decarb.targetReduction')} <span className="text-red-500">*</span></label>
                  <input type="number" min="0" max="100" step="0.1" className={cn('input', errors.targetReduction && 'border-red-400')} {...register('targetReduction', { required: true })} />
                </div>
              </div>
              <div className="flex items-center gap-2">
                <input type="checkbox" id="sbtiAligned" {...register('sbtiAligned')} className="w-4 h-4 accent-accent" />
                <label htmlFor="sbtiAligned" className="text-sm text-text-primary">{t('decarb.sbtiAligned')}</label>
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
