'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { tradeGoodsApi } from '@/lib/api'
import { useLang } from '@/contexts/LangContext'
import { formatDate, cn } from '@/lib/utils'
import { Plus, Search, ChevronLeft, ChevronRight, X, AlertCircle } from 'lucide-react'

interface TradeGoodRow {
  id: string
  supplierId: string
  supplier?: { name: string }
  name: string
  nameZh?: string
  hsCode: string
  cbamApplicable: boolean
  cbamSector?: string
  annualVolume?: number
  unit?: string
  countryOfOrigin: string
  createdAt: string
}

interface CreateTradeGoodForm {
  supplierId: string
  name: string
  nameZh: string
  hsCode: string
  cbamApplicable: boolean
  cbamSector: string
  annualVolume: string
  unit: string
  countryOfOrigin: string
}

const CBAM_SECTORS = ['cement', 'steel', 'aluminium', 'fertiliser', 'electricity', 'hydrogen']
const PAGE_SIZE = 15

export function TradeGoodsView() {
  const { t } = useLang()
  const queryClient = useQueryClient()

  const [search, setSearch] = useState('')
  const [filterCbam, setFilterCbam] = useState('')
  const [filterSector, setFilterSector] = useState('')
  const [page, setPage] = useState(1)
  const [showCreateModal, setShowCreateModal] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['tradegoods', { search, filterCbam, filterSector, page }],
    queryFn: () => tradeGoodsApi.list({
      search: search || undefined,
      cbamApplicable: filterCbam === '' ? undefined : filterCbam === 'true',
      cbamSector: filterSector || undefined,
      page,
      limit: PAGE_SIZE,
    }).then(r => r.data),
  })

  const goods: TradeGoodRow[] = data?.data ?? []
  const total: number = data?.total ?? 0
  const totalPages = Math.ceil(total / PAGE_SIZE)
  const cbamCount = goods.filter(g => g.cbamApplicable).length
  const cbamSectors = [...new Set(goods.filter(g => g.cbamSector).map(g => g.cbamSector!))]

  const { register, handleSubmit, reset, watch, formState: { errors } } = useForm<CreateTradeGoodForm>({
    defaultValues: { cbamApplicable: false }
  })
  const watchCbam = watch('cbamApplicable')

  const createMutation = useMutation({
    mutationFn: (data: CreateTradeGoodForm) => tradeGoodsApi.create({
      ...data,
      annualVolume: data.annualVolume ? Number(data.annualVolume) : undefined,
      cbamSector: data.cbamApplicable ? data.cbamSector : undefined,
    }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tradegoods'] })
      setShowCreateModal(false)
      reset()
    },
  })

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="font-heading text-2xl font-semibold text-text-primary">{t('tradegoods.title')}</h1>
        <button onClick={() => setShowCreateModal(true)} className="btn-primary flex items-center gap-2">
          <Plus size={16} />
          {t('tradegoods.new')}
        </button>
      </div>

      {/* CBAM Summary */}
      <div className="card p-4 flex items-center gap-4 bg-amber-50 border-amber-200">
        <AlertCircle size={20} className="text-amber-600 shrink-0" />
        <div>
          <div className="font-medium text-amber-900 text-sm">{t('tradegoods.cbamExposure')}</div>
          <div className="text-xs text-amber-700 mt-0.5">
            {t('tradegoods.cbamCount')}: <strong>{cbamCount}</strong>
            {cbamSectors.length > 0 && ` | 類別: ${cbamSectors.map(s => t(`cbamSector.${s}`)).join(', ')}`}
          </div>
        </div>
      </div>

      {/* Filters */}
      <div className="card p-4 flex flex-wrap gap-3 items-center">
        <div className="relative flex-1 min-w-48">
          <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" />
          <input
            className="input pl-9"
            placeholder={t('tradegoods.searchPlaceholder')}
            value={search}
            onChange={e => { setSearch(e.target.value); setPage(1) }}
          />
        </div>
        <select className="input w-40" value={filterCbam} onChange={e => { setFilterCbam(e.target.value); setPage(1) }}>
          <option value="">{t('common.all')}</option>
          <option value="true">{t('tradegoods.cbamYes')}</option>
          <option value="false">{t('tradegoods.cbamNo')}</option>
        </select>
        <select className="input w-40" value={filterSector} onChange={e => { setFilterSector(e.target.value); setPage(1) }}>
          <option value="">{t('common.all')}</option>
          {CBAM_SECTORS.map(s => <option key={s} value={s}>{t(`cbamSector.${s}`)}</option>)}
        </select>
      </div>

      {/* Table */}
      <div className="card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-surface-2 border-b border-border">
              <tr>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('tradegoods.name')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('tradegoods.hsCode')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('tradegoods.supplier')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('tradegoods.cbamApplicable')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('tradegoods.cbamSector')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('tradegoods.countryOfOrigin')}</th>
                <th className="text-right px-4 py-3 text-xs font-medium text-text-secondary">{t('tradegoods.annualVolume')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('common.createdAt')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {isLoading ? (
                <tr><td colSpan={8} className="text-center py-12 text-text-secondary">{t('common.loading')}</td></tr>
              ) : goods.length === 0 ? (
                <tr><td colSpan={8} className="text-center py-12 text-text-secondary">{t('common.noData')}</td></tr>
              ) : (
                goods.map(good => (
                  <tr key={good.id} className="hover:bg-surface-2 transition-colors">
                    <td className="px-4 py-3">
                      <div className="font-medium text-text-primary">{good.name}</div>
                      {good.nameZh && <div className="text-xs text-text-secondary">{good.nameZh}</div>}
                    </td>
                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">{good.hsCode}</td>
                    <td className="px-4 py-3 text-text-secondary">{good.supplier?.name ?? good.supplierId}</td>
                    <td className="px-4 py-3">
                      {good.cbamApplicable ? (
                        <span className="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">CBAM</span>
                      ) : (
                        <span className="text-xs bg-stone-100 text-stone-500 px-2 py-0.5 rounded-full">{t('tradegoods.cbamNo')}</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-text-secondary text-xs">
                      {good.cbamSector ? t(`cbamSector.${good.cbamSector}`) : '—'}
                    </td>
                    <td className="px-4 py-3 text-text-secondary">{good.countryOfOrigin}</td>
                    <td className="px-4 py-3 text-right font-mono text-xs">
                      {good.annualVolume != null ? `${good.annualVolume.toLocaleString()} ${good.unit ?? ''}` : '—'}
                    </td>
                    <td className="px-4 py-3 text-text-secondary text-xs">{formatDate(good.createdAt)}</td>
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
              <h2 className="font-heading font-semibold text-text-primary">{t('tradegoods.new')}</h2>
              <button onClick={() => { setShowCreateModal(false); reset() }} className="p-1.5 rounded hover:bg-surface-2"><X size={18} /></button>
            </div>
            <form onSubmit={handleSubmit(d => createMutation.mutate(d))} className="px-6 py-5 space-y-4">
              <div>
                <label className="label">{t('tradegoods.supplier')} <span className="text-red-500">*</span></label>
                <input className={cn('input', errors.supplierId && 'border-red-400')} {...register('supplierId', { required: true })} />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label">{t('tradegoods.name')} <span className="text-red-500">*</span></label>
                  <input className={cn('input', errors.name && 'border-red-400')} {...register('name', { required: true })} />
                </div>
                <div>
                  <label className="label">{t('tradegoods.nameZh')}</label>
                  <input className="input" {...register('nameZh')} />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label">{t('tradegoods.hsCode')} <span className="text-red-500">*</span></label>
                  <input className={cn('input font-mono', errors.hsCode && 'border-red-400')} {...register('hsCode', { required: true })} placeholder="7208.51" />
                </div>
                <div>
                  <label className="label">{t('tradegoods.countryOfOrigin')} <span className="text-red-500">*</span></label>
                  <input className={cn('input', errors.countryOfOrigin && 'border-red-400')} {...register('countryOfOrigin', { required: true })} placeholder="TW" />
                </div>
              </div>
              <div className="flex items-center gap-2">
                <input type="checkbox" id="cbamApplicable" {...register('cbamApplicable')} className="w-4 h-4 accent-accent" />
                <label htmlFor="cbamApplicable" className="text-sm text-text-primary">{t('tradegoods.cbamApplicable')}</label>
              </div>
              {watchCbam && (
                <div>
                  <label className="label">{t('tradegoods.cbamSector')}</label>
                  <select className="input" {...register('cbamSector')}>
                    <option value="">{t('common.all')}</option>
                    {CBAM_SECTORS.map(s => <option key={s} value={s}>{t(`cbamSector.${s}`)}</option>)}
                  </select>
                </div>
              )}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label">{t('tradegoods.annualVolume')}</label>
                  <input type="number" className="input" {...register('annualVolume')} />
                </div>
                <div>
                  <label className="label">{t('tradegoods.unit')}</label>
                  <input className="input" {...register('unit')} placeholder="公噸" />
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
