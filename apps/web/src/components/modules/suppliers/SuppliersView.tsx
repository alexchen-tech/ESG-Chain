'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useRouter } from 'next/navigation'
import { useSession } from 'next-auth/react'
import { useForm } from 'react-hook-form'
import { suppliersApi } from '@/lib/api'
import { useLang } from '@/contexts/LangContext'
import { riskBadgeClass, supplierStatusClass, formatDate, cn } from '@/lib/utils'
import { RiskLevel, SupplierStatus, SupplierTier, UserRole } from '@esg-chain/types'
import { Plus, Search, ChevronLeft, ChevronRight, X, Eye } from 'lucide-react'

interface SupplierRow {
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
  carbonIntensity?: number
  contactEmail?: string
  website?: string
  createdAt: string
}

interface CreateSupplierForm {
  name: string
  nameEn: string
  tier: SupplierTier
  country: string
  industry: string
  contactEmail: string
  website: string
  employeeCount: string
}

const PAGE_SIZE = 15

export function SuppliersView() {
  const { t } = useLang()
  const router = useRouter()
  const { data: session } = useSession()
  const queryClient = useQueryClient()

  const [search, setSearch] = useState('')
  const [filterTier, setFilterTier] = useState('')
  const [filterStatus, setFilterStatus] = useState('')
  const [filterRisk, setFilterRisk] = useState('')
  const [page, setPage] = useState(1)
  const [showCreateModal, setShowCreateModal] = useState(false)

  const userRole = (session?.user as any)?.role as UserRole
  const canCreate = [UserRole.ADMIN, UserRole.BUYER].includes(userRole)

  const { data, isLoading } = useQuery({
    queryKey: ['suppliers', { search, filterTier, filterStatus, filterRisk, page }],
    queryFn: () => suppliersApi.list({
      search: search || undefined,
      tier: filterTier || undefined,
      status: filterStatus || undefined,
      riskLevel: filterRisk || undefined,
      page,
      limit: PAGE_SIZE,
    }).then(r => r.data),
  })

  const suppliers: SupplierRow[] = data?.data ?? []
  const total: number = data?.total ?? 0
  const totalPages = Math.ceil(total / PAGE_SIZE)

  const { register, handleSubmit, reset, formState: { errors } } = useForm<CreateSupplierForm>()

  const createMutation = useMutation({
    mutationFn: (formData: CreateSupplierForm) => suppliersApi.create({
      ...formData,
      employeeCount: formData.employeeCount ? Number(formData.employeeCount) : undefined,
    }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['suppliers'] })
      setShowCreateModal(false)
      reset()
    },
  })

  const onSubmitCreate = (data: CreateSupplierForm) => {
    createMutation.mutate(data)
  }

  const tierOptions = [
    { value: '', label: t('common.all') },
    { value: SupplierTier.TIER1, label: t('tier.tier1') },
    { value: SupplierTier.TIER2, label: t('tier.tier2') },
    { value: SupplierTier.TIER3, label: t('tier.tier3') },
  ]

  const statusOptions = [
    { value: '', label: t('common.all') },
    { value: SupplierStatus.ACTIVE, label: t('supplierStatus.active') },
    { value: SupplierStatus.INACTIVE, label: t('supplierStatus.inactive') },
    { value: SupplierStatus.PENDING, label: t('supplierStatus.pending') },
    { value: SupplierStatus.SUSPENDED, label: t('supplierStatus.suspended') },
  ]

  const riskOptions = [
    { value: '', label: t('common.all') },
    { value: RiskLevel.LOW, label: t('risk.low') },
    { value: RiskLevel.MEDIUM, label: t('risk.medium') },
    { value: RiskLevel.HIGH, label: t('risk.high') },
    { value: RiskLevel.CRITICAL, label: t('risk.critical') },
  ]

  return (
    <div className="space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="font-heading text-2xl font-semibold text-text-primary">{t('supplier.title')}</h1>
        {canCreate && (
          <button
            onClick={() => setShowCreateModal(true)}
            className="btn-primary flex items-center gap-2"
          >
            <Plus size={16} />
            {t('supplier.new')}
          </button>
        )}
      </div>

      {/* Filters */}
      <div className="card p-4 flex flex-wrap gap-3 items-center">
        <div className="relative flex-1 min-w-48">
          <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" />
          <input
            className="input pl-9"
            placeholder={t('supplier.searchPlaceholder')}
            value={search}
            onChange={e => { setSearch(e.target.value); setPage(1) }}
          />
        </div>
        <select
          className="input w-36"
          value={filterTier}
          onChange={e => { setFilterTier(e.target.value); setPage(1) }}
        >
          {tierOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select
          className="input w-36"
          value={filterStatus}
          onChange={e => { setFilterStatus(e.target.value); setPage(1) }}
        >
          {statusOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select
          className="input w-36"
          value={filterRisk}
          onChange={e => { setFilterRisk(e.target.value); setPage(1) }}
        >
          {riskOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {/* Table */}
      <div className="card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-surface-2 border-b border-border">
              <tr>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('supplier.code')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('supplier.name')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('supplier.tier')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('common.country')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('supplier.riskLevel')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('supplier.status')}</th>
                <th className="text-right px-4 py-3 text-xs font-medium text-text-secondary">{t('supplier.carbonIntensity')}</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-text-secondary">{t('common.createdAt')}</th>
                <th className="text-center px-4 py-3 text-xs font-medium text-text-secondary">{t('common.actions')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {isLoading ? (
                <tr>
                  <td colSpan={9} className="text-center py-12 text-text-secondary">{t('common.loading')}</td>
                </tr>
              ) : suppliers.length === 0 ? (
                <tr>
                  <td colSpan={9} className="text-center py-12 text-text-secondary">{t('common.noData')}</td>
                </tr>
              ) : (
                suppliers.map(supplier => (
                  <tr
                    key={supplier.id}
                    className="hover:bg-surface-2 transition-colors cursor-pointer"
                    onClick={() => router.push(`/suppliers/${supplier.id}`)}
                  >
                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">{supplier.code}</td>
                    <td className="px-4 py-3">
                      <div className="font-medium text-text-primary">{supplier.name}</div>
                      {supplier.nameEn && <div className="text-xs text-text-secondary">{supplier.nameEn}</div>}
                    </td>
                    <td className="px-4 py-3">
                      <span className="text-xs bg-stone-100 text-stone-600 px-2 py-0.5 rounded-full">
                        {t(`tier.${supplier.tier}`)}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-text-secondary">{supplier.country}</td>
                    <td className="px-4 py-3">
                      <span className={riskBadgeClass(supplier.riskLevel)}>
                        {t(`risk.${supplier.riskLevel}`)}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <span className={supplierStatusClass(supplier.status)}>
                        {t(`supplierStatus.${supplier.status}`)}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right font-mono text-xs">
                      {supplier.carbonIntensity != null ? supplier.carbonIntensity.toFixed(3) : '—'}
                    </td>
                    <td className="px-4 py-3 text-text-secondary text-xs">{formatDate(supplier.createdAt)}</td>
                    <td className="px-4 py-3 text-center" onClick={e => e.stopPropagation()}>
                      <button
                        onClick={() => router.push(`/suppliers/${supplier.id}`)}
                        className="p-1.5 rounded hover:bg-accent-light text-text-secondary hover:text-accent transition-colors"
                        title={t('common.view')}
                      >
                        <Eye size={15} />
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <div className="flex items-center justify-between px-4 py-3 border-t border-border">
          <span className="text-xs text-text-secondary">
            {t('common.total')} {total} {t('common.items')}
          </span>
          <div className="flex items-center gap-2">
            <button
              onClick={() => setPage(p => Math.max(1, p - 1))}
              disabled={page <= 1}
              className="p-1.5 rounded hover:bg-surface-2 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <ChevronLeft size={16} />
            </button>
            <span className="text-xs text-text-secondary">{page} / {totalPages || 1}</span>
            <button
              onClick={() => setPage(p => Math.min(totalPages, p + 1))}
              disabled={page >= totalPages}
              className="p-1.5 rounded hover:bg-surface-2 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <ChevronRight size={16} />
            </button>
          </div>
        </div>
      </div>

      {/* Create Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
          <div className="bg-surface rounded-card shadow-modal w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between px-6 py-4 border-b border-border">
              <h2 className="font-heading font-semibold text-text-primary">{t('supplier.new')}</h2>
              <button onClick={() => { setShowCreateModal(false); reset() }} className="p-1.5 rounded hover:bg-surface-2">
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleSubmit(onSubmitCreate)} className="px-6 py-5 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="col-span-2">
                  <label className="label">{t('supplier.name')} <span className="text-red-500">*</span></label>
                  <input
                    className={cn('input', errors.name && 'border-red-400')}
                    {...register('name', { required: true })}
                    placeholder="例：台積電供應鏈 A 廠"
                  />
                </div>
                <div className="col-span-2">
                  <label className="label">{t('supplier.nameEn')}</label>
                  <input className="input" {...register('nameEn')} placeholder="e.g. TSMC Supplier A" />
                </div>
                <div>
                  <label className="label">{t('supplier.tier')} <span className="text-red-500">*</span></label>
                  <select className="input" {...register('tier', { required: true })}>
                    <option value={SupplierTier.TIER1}>{t('tier.tier1')}</option>
                    <option value={SupplierTier.TIER2}>{t('tier.tier2')}</option>
                    <option value={SupplierTier.TIER3}>{t('tier.tier3')}</option>
                  </select>
                </div>
                <div>
                  <label className="label">{t('common.country')} <span className="text-red-500">*</span></label>
                  <input
                    className={cn('input', errors.country && 'border-red-400')}
                    {...register('country', { required: true })}
                    placeholder="TW"
                  />
                </div>
                <div className="col-span-2">
                  <label className="label">{t('common.industry')} <span className="text-red-500">*</span></label>
                  <input
                    className={cn('input', errors.industry && 'border-red-400')}
                    {...register('industry', { required: true })}
                    placeholder="半導體製造"
                  />
                </div>
                <div>
                  <label className="label">{t('supplier.contactEmail')}</label>
                  <input className="input" type="email" {...register('contactEmail')} placeholder="contact@supplier.com" />
                </div>
                <div>
                  <label className="label">{t('supplier.website')}</label>
                  <input className="input" {...register('website')} placeholder="https://..." />
                </div>
                <div>
                  <label className="label">{t('supplier.employeeCount')}</label>
                  <input className="input" type="number" {...register('employeeCount')} placeholder="1000" />
                </div>
              </div>

              {createMutation.isError && (
                <p className="text-sm text-red-600">建立失敗，請稍後再試</p>
              )}

              <div className="flex justify-end gap-3 pt-2">
                <button type="button" onClick={() => { setShowCreateModal(false); reset() }} className="btn-secondary">
                  {t('common.cancel')}
                </button>
                <button type="submit" className="btn-primary" disabled={createMutation.isPending}>
                  {createMutation.isPending ? t('common.loading') : t('common.save')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
