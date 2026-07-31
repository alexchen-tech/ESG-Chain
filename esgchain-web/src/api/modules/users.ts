import http from '@/api/http'
import type { UserPermissions } from '@/api/modules/permissions'

export type InternalRole = 'admin' | 'buyer' | 'sustain' | 'comply' | 'analyst'

export interface ManagedUser {
  id: string
  name: string
  email: string
  supplier_id: string | null
  is_active: boolean
  organization_unit_id: string | null
  roles: string[]
  created_at: string
  updated_at: string
}

export interface UserListParams {
  page?: number
  per_page?: number
  role?: string
  is_active?: boolean | string
  q?: string
}

export const usersApi = {
  list: (params?: UserListParams) =>
    http.get<{ success: boolean; data: ManagedUser[]; pagination: any }>('/api/v1/users', { params }),

  create: (data: { name: string; email: string; role: InternalRole }) =>
    http.post<{ success: boolean; data: ManagedUser; message: string }>('/api/v1/users', data),

  updateRoles: (userId: string, roles: string[], reason?: string) =>
    http.put<{ success: boolean; data: ManagedUser; message: string }>(`/api/v1/users/${userId}/roles`, { roles, reason }),

  toggleActive: (userId: string, isActive: boolean, reason?: string) =>
    http.post<{ success: boolean; data: ManagedUser; warnings: string[]; message: string }>(
      `/api/v1/users/${userId}/toggle-active`,
      { is_active: isActive, reason }
    ),

  resetPassword: (userId: string) =>
    http.post<{ success: boolean; data: { new_password: string }; message: string }>(
      `/api/v1/users/${userId}/reset-password`
    ),

  // 個人權限覆寫（限 admin，見 openspec/changes/crud-permission-granularity design.md Decision 2：
  // 僅支援多授予，不支援從角色權限中負向收回）
  permissions: (userId: string) =>
    http.get<{ success: boolean; data: UserPermissions }>(`/api/v1/users/${userId}/permissions`),

  grantPermission: (userId: string, permission: string) =>
    http.post<{ success: boolean; data: UserPermissions; message: string }>(
      `/api/v1/users/${userId}/permissions/${encodeURIComponent(permission)}`
    ),

  revokePermission: (userId: string, permission: string) =>
    http.delete<{ success: boolean; data: UserPermissions; message: string }>(
      `/api/v1/users/${userId}/permissions/${encodeURIComponent(permission)}`
    ),
}
