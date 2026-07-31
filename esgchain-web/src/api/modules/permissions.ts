import http from '@/api/http'

export interface PermissionItem {
  name: string
  description: string
}

export type PermissionCatalog = Record<string, PermissionItem[]>

export interface RolePermissions {
  role: string
  permissions: string[]
  locked: boolean
}

/**
 * 拆分後的權限目錄以「模組」分組，同一模組內依動作（view/create/update/delete，或
 * publish 等既有特例動作）展開多個權限字串。此輔助函式把單一模組的權限陣列，
 * 依權限字串最後一段（動作）分組，供 RolesView / UsersView 需要「依動作」呈現時使用。
 */
export function groupByAction(items: PermissionItem[]): Record<string, PermissionItem[]> {
  const grouped: Record<string, PermissionItem[]> = {}
  for (const item of items) {
    const action = item.name.split('.').pop() || item.name
    if (!grouped[action]) grouped[action] = []
    grouped[action].push(item)
  }
  return grouped
}

export interface UserPermissions {
  user_id: string
  is_admin: boolean
  role_permissions: string[]
  direct_permissions: string[]
}

export const permissionsApi = {
  catalog: () => http.get<{ success: boolean; data: PermissionCatalog }>('/api/v1/settings/permissions'),

  rolePermissions: (role: string) =>
    http.get<{ success: boolean; data: RolePermissions }>(`/api/v1/settings/roles/${role}/permissions`),

  updateRolePermissions: (role: string, permissions: string[]) =>
    http.put<{ success: boolean; data: RolePermissions; message: string }>(
      `/api/v1/settings/roles/${role}/permissions`,
      { permissions },
    ),
}
