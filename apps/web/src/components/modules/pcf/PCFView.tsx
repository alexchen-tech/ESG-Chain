'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { pcfApi } from '@/lib/api'
import { useLang } from '@/contexts/LangContext'
import { formatDate, cn } from '@/lib/utils'
import { PCFStatus } from '@esg-chain/types'
import { Plus, Search, ChevronLeft, ChevronRight, X } from 'lucide-react'

interface PCFRow {
  id: string
  supplierId: string
  supplier?: { name: string }
  productName: string
  functionalUnit: string
  scope1KgCO2e: number
  scope2KgCO2e: number
  scope3KgCO2e: number
  totalKgCO2e: number
  year: number
  status: PCFStatus
  methodology?: string
  createdAt: string
}

interface CreatePCFForm {
  supplierId: string
  productName: string
  functionalUnit: string
  scope1KgCO2e: string
  scope2KgCO2e: string
  scope3KgCO2e: string
  year: string
  methodology: string
}

const PAGE_SIZE = 15

function pcfStatusBadge(status: PCFStatus) {
  const map: Record<PCFStatus, string> = {
    [PCFStatus.DRAFT]: 'bg-stone-100 text-stone-500',
    [PCFStatus.SUBMITTED]: 'bg-blue-100 text-blue-700',
    [PCFStatus.VERIFIED]: 'bg-green-100 text-green-700',
  }
  return `text-xs font-medium px-2 py-0.5 rounded-full ${map[status] ?? ''}`
}

export function PCFView() {
  const { t } = useLang()
  const queryClient = useQueryClient()

  const [search, setSearch] = useState('')
  const [filterStatus, setFilterStatus] = useState('')
  const [page, setPage] = useState(1)
  const [showCreateModal, setShowCreateModal] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['pcf', { search, filterStatus, page }],
    queryFn: () => pcfApi.list({
      search: search || undefined,
      status: filterStatus || undefined,
      page,
      limit: PAGE_SIZE,
    }).then(r => r.data),
  })

  const records: PCFRow[] = data?.data ?? []
  const total: number = data?.total ?? 0
  const totalPages = Math.ceil(total / PAGE_SIZE)

  const avgEmission = records.length > 0 ? records.reduce((s, r) => s + r.totalKgCO2e, 0) / records.length : 0
  const verifiedCount = records.filter(r => r.status === PCFStatus.VERIFIED).length

  const { register, handleSubmit, reset, watch, formState: { errors } } = useForm<CreatePCFForm>({
    defaultValues: { year: String(new Date().getFullYear()) }
  })

  const scope1 = Number(watch('scope1KgCO2e') || 0)
  const scope2 = Number(watch('scope2KgCO2e') || 0)
  const scope3 = Number(watch('scope3KgCO2e') || 0)
  const calculatedTotal = scope1 + scope2 + scope3

  const createMutation = useMutation({
    mutationFn: (data: CreatePCFForm) => pcfApi.create({
      ...data,
      scope1KgCO2e: Number(data.scope1KgCO2e),
      scope2KgCO2e: Number(data.scope2KgCO2e),
      scope3KgCO2e: Number(data.scope3KgCO2e),
      totalKgCO2e: Number(data.scope1KgCO2e) + Number(data.scope2KgCO2e) + Number(data.scope3KgCO2e),
      year: Number(data.year),
    }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['pcf'] })
      setShowCreateModal(false)
      reset()
    },
  })

  const statusOptions = [
    { value: '', label: t('common.all') },
    { value: PCFStatus.DRAFT, label: t('pcfStatus.draft') },
    { value: PCFStatus.SUBMITTED, label: t('pcfStatus.submitted') },
    { value: PCFStatus.VERIFIED, label: t('pcfStatus.verified') },
  ]

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="font-heading text-2xl font-semibold text-text-primary">{t('pcf.title')}</h1>
        <button onClick={() => setShowCreateModal(true)} className="btn-primary flex items-center gap-2">
          <Plus size={16} />
          {t('pcf.new')}
        </button>
      </div>

      {/* Summary */}
      <div className="grid grid-cols-3 gap-4">
        <div className="card p-4">
          <div className="text-2xl font-heading font-bold text-blue-600">{total}</div>
          <div className="text-xs text-text-secondary mt-1">{t('pcf.totalRecords')}</div>
        </div>
        <div className="card p-4">
          <div className="text-2xl font-heading font-bold text-teal-600">{avgEmission.toFixed(1)}</div>
          <div className="text-xs text-text-secondary mt-1">{t('pcf.avgEmission')} kgCO2e</div>
        </div>
        <div className="card p-4">
          <div className="text-2xl font-heading font-bold text-green-600">{verifiedCount}</div>
          <div className="text-xs text-text-secondary mt-1">{t('pcf.verifiedCount')}</div>
        </div>
      </div>

      {/* Filters */}
      <div className="card p-4 flex flex-wrap gap-3 items-center">
        <div className="relative flex-1 min-w-48">
          <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" />
          <input
            className="input pl-9"
            placeholder={t('pcf.searchPlaceholder')}
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
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('pcf.product')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('pcf.supplier')}</th>
                <th className="text-right px-4 py-3 text-xs font-medium text-text-secondary">{t('pcf.year')}</th>
                <th className="text-right px-4 py-3 text-xs font-medium text-text-secondary">{t('pcf.scope1Label')}</th>
                <th className="text-right px-4 py-3 text-xs font-medium text-text-secondary">{t('pcf.scope2Label')}</th>
                <th className="text-right px-4 py-3 text-xs font-medium text-text-secondary">{t('pcf.scope3Label')}</th>
                <th className="text-right px-4 py-3 text-xs font-medium text-text-secondary">{t('pcf.total')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('pcf.status')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('common.createdAt')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {isLoading ? (
                <tr><td colSpan={9} className="text-center py-12 text-text-secondary">{t('common.loading')}</td></tr>
              ) : records.length === 0 ? (
                <tr><td colSpan={9} className="text-center py-12 text-text-secondary">{t('common.noData')}</td></tr>
              ) : (
                records.map(rec => (
                  <tr key={rec.id} className="hover:bg-surface-2 transition-colors">
                    <td className="px-4 py-3">
                      <div className="font-medium text-text-primary">{rec.productName}</div>
                      <div className="text-xs text-text-secondary">{rec.functionalUnit}</div>
                    </td>
                    <td className="px-4 py-3 text-text-secondary">{rec.supplier?.name ?? rec.supplierId}</td>
                    <td className="px-4 py-3 text-right font-mono text-sm">{rec.year}</td>
                    <td className="px-4 py-3 text-right font-mono text-xs text-blue-600">{rec.scope1KgCO2e.toFixed(1)}</td>
                    <td className="px-4 py-3 text-right font-mono text-xs text-teal-600">{rec.scope2KgCO2e.toFixed(1)}</td>
                    <td className="px-4 py-3 text-right font-mono text-xs text-purple-600">{rec.scope3KgCO2e.toFixed(1)}</td>
                    <td className="px-4 py-3 text-right font-mono text-xs font-semibold text-text-primary">{rec.totalKgCO2e.toFixed(1)}</td>
                    <td className="px-4 py-3">
                      <span className={pcfStatusBadge(rec.status)}>{t(`pcfStatus.${rec.status}`)}</span>
                    </td>
                    <td className="px-4 py-3 text-text-secondary text-xs">{formatDate(rec.createdAt)}</td>
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
          <div className="bg-surface rounded-card shadow-modal w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between px-6 py-4 border-b border-border">
              <h2 className="font-heading font-semibold text-text-primary">{t('pcf.new')}</h2>
              <button onClick={() => { setShowCreateModal(false); reset() }} className="p-1.5 rounded hover:bg-surface-2"><X size={18} /></button>
            </div>
            <form onSubmit={handleSubmit(d => createMutation.mutate(d))} className="px-6 py-5 space-y-4">
              <div>
                <label className="label">{t('pcf.supplier')} <span className="text-red-500">*</span></label>
                <input className={cn('input', errors.supplierId && 'border-red-400')} {...register('supplierId', { required: true })} />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label">{t('pcf.productName')} <span className="text-red-500">*</span></label>
                  <input className={cn('input', errors.productName && 'border-red-400')} {...register('productName', { required: true })} />
                </div>
                <div>
                  <label className="label">{t('pcf.functionalUnit')} <span className="text-red-500">*</span></label>
                  <input className={cn('input', errors.functionalUnit && 'border-red-400')} {...register('functionalUnit', { required: true })} placeholder="1 公噸產品" />
                </div>
              </div>
              <div className="grid grid-cols-3 gap-4">
                <div>
                  <label className="label">{t('pcf.scope1')} <span className="text-red-500">*</span></label>
                  <input type="number" step="0.001" className={cn('input font-mono', errors.scope1KgCO2e && 'border-red-400')} {...register('scope1KgCO2e', { required: true, min: 0 })} defaultValue={0} />
                </div>
                <div>
                  <label className="label">{t('pcf.scope2')} <span className="text-red-500">*</span></label>
                  <input type="number" step="0.001" className={cn('input font-mono', errors.scope2KgCO2e && 'border-red-400')} {...register('scope2KgCO2e', { required: true, min: 0 })} defaultValue={0} />
                </div>
                <div>
                  <label className="label">{t('pcf.scope3')} <span className="text-red-500">*</span></label>
                  <input type="number" step="0.001" className={cn('input font-mono', errors.scope3KgCO2e && 'border-red-400')} {...register('scope3KgCO2e', { required: true, min: 0 })} defaultValue={0} />
                </div>
              </div>
              <div className="bg-surface-2 rounded-btn px-3 py-2 flex items-center justify-between">
                <span className="text-xs text-text-secondary">{t('pcf.total')} (自動計算)</span>
                <span className="font-mono font-semibold text-accent">{calculatedTotal.toFixed(3)} kgCO2e</span>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label">{t('pcf.year')} <span className="text-red-500">*</span></label>
                  <input type="number" className={cn('input', errors.year && 'border-red-400')} {...register('year', { required: true })} />
                </div>
                <div>
                  <label className="label">{t('pcf.methodology')}</label>
                  <input className="input" {...register('methodology')} placeholder="GHG Protocol" />
                </div>
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
