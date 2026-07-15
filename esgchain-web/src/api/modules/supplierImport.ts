import http from '@/api/http'

export type ImportCleansStatus = 'staged' | 'cleansed' | 'rejected' | 'approved' | 'exempt'

export interface SupplierImport {
  id: string
  batch_id: string
  vendor_code: string | null
  vat_number: string | null
  vendor_name: string
  spend_amount: number | null
  country_code: string | null
  material_group: string | null
  primary_email: string | null
  cleanse_status: ImportCleansStatus
  failure_codes: string[] | null
  notes: string | null
  erp_vendor_codes: string[] | null
  created_at: string
}

export interface ImportBatchStatus {
  batch_id: string
  total: number
  staged: number
  cleansed: number
  rejected: number
  exempt: number
  approved: number
}

export const FAILURE_CODE_LABELS: Record<string, string> = {
  email_invalid:          'Email 空白或格式錯誤',
  duplicate_vat_merged:   '重複 VAT（已合併至主記錄）',
  vat_exists_in_master:   'VAT 已存在供應商主表',
}

export const supplierImportApi = {
  upload: (file: File) => {
    const form = new FormData()
    form.append('file', file)
    return http.post<{ success: boolean; batch_id: string; total: number; cleansed: number; rejected: number }>(
      '/api/v1/suppliers/import', form, { headers: { 'Content-Type': 'multipart/form-data' } })
  },

  status: (batchId: string) =>
    http.get<{ success: boolean } & ImportBatchStatus>(`/api/v1/suppliers/import/${batchId}/status`),

  list: (batchId: string, cleanse_status?: ImportCleansStatus) =>
    http.get<{ success: boolean; data: SupplierImport[] }>(
      `/api/v1/suppliers/import/${batchId}/items`,
      { params: cleanse_status ? { cleanse_status } : {} }),

  updateItem: (batchId: string, id: string, data: { primary_email?: string; notes?: string }) =>
    http.put<{ success: boolean; data: SupplierImport }>(
      `/api/v1/suppliers/import/${batchId}/items/${id}`, data),

  exempt: (batchId: string, id: string, notes: string) =>
    http.post<{ success: boolean; data: SupplierImport }>(
      `/api/v1/suppliers/import/${batchId}/items/${id}/exempt`, { notes }),

  approve: (batchId: string) =>
    http.post<{ success: boolean; message: string; data: { approved_count: number; skipped_count: number } }>(
      `/api/v1/suppliers/import/${batchId}/approve`),
}
