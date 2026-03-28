'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { useSession } from 'next-auth/react'
import { useLang } from '@/contexts/LangContext'
import { ROLE_MODULE_ACCESS, UserRole } from '@esg-chain/types'
import {
  LayoutDashboard, Users, ClipboardList, AlertTriangle,
  Package, Leaf, TrendingDown, FileText, Settings, ExternalLink,
  ChevronLeft, ChevronRight
} from 'lucide-react'
import { useState } from 'react'
import { cn } from '@/lib/utils'

const ALL_NAV_ITEMS = [
  { module: 'dashboard',  href: '/dashboard',  icon: LayoutDashboard, labelKey: 'nav.dashboard' },
  { module: 'suppliers',  href: '/suppliers',  icon: Users,           labelKey: 'nav.suppliers' },
  { module: 'saq',        href: '/saq',        icon: ClipboardList,   labelKey: 'nav.saq' },
  { module: 'cap',        href: '/cap',        icon: AlertTriangle,   labelKey: 'nav.cap' },
  { module: 'tradegoods', href: '/tradegoods', icon: Package,         labelKey: 'nav.tradegoods' },
  { module: 'pcf',        href: '/pcf',        icon: Leaf,            labelKey: 'nav.pcf' },
  { module: 'decarb',     href: '/decarb',     icon: TrendingDown,    labelKey: 'nav.decarb' },
  { module: 'reports',    href: '/reports',    icon: FileText,        labelKey: 'nav.reports' },
  { module: 'settings',   href: '/settings',   icon: Settings,        labelKey: 'nav.settings' },
  { module: 'portal',     href: '/portal',     icon: ExternalLink,    labelKey: 'nav.portal' },
]

export function Sidebar() {
  const pathname = usePathname()
  const { data: session } = useSession()
  const { t } = useLang()
  const [collapsed, setCollapsed] = useState(false)

  const userRole = ((session?.user as any)?.role as UserRole) ?? UserRole.ANALYST
  const allowedModules = ROLE_MODULE_ACCESS[userRole] ?? []
  const navItems = ALL_NAV_ITEMS.filter(item => allowedModules.includes(item.module))

  return (
    <aside
      className={cn(
        'flex flex-col h-full bg-sidebar-bg text-sidebar-text transition-all duration-300 shrink-0',
        collapsed ? 'w-16' : 'w-60'
      )}
    >
      {/* Logo */}
      <div className="flex items-center justify-between px-4 py-5 border-b border-white/10">
        {!collapsed && (
          <div>
            <div className="font-heading font-bold text-white text-lg tracking-tight">ESG·Chain</div>
            <div className="text-xs text-sidebar-text/60 mt-0.5">永續供應鏈管理</div>
          </div>
        )}
        <button
          onClick={() => setCollapsed(!collapsed)}
          className="p-1.5 rounded hover:bg-white/10 text-sidebar-text/60 hover:text-white transition-colors ml-auto"
        >
          {collapsed ? <ChevronRight size={16} /> : <ChevronLeft size={16} />}
        </button>
      </div>

      {/* Navigation */}
      <nav className="flex-1 py-4 space-y-0.5 overflow-y-auto">
        {navItems.map((item) => {
          const Icon = item.icon
          const isActive = pathname === item.href || pathname.startsWith(item.href + '/')
          return (
            <Link
              key={item.module}
              href={item.href}
              className={cn(
                'flex items-center gap-3 px-4 py-2.5 text-sm transition-colors',
                isActive
                  ? 'bg-sidebar-active text-white font-medium'
                  : 'text-sidebar-text hover:bg-white/10 hover:text-white'
              )}
              title={collapsed ? t(item.labelKey) : undefined}
            >
              <Icon size={18} className="shrink-0" />
              {!collapsed && <span>{t(item.labelKey)}</span>}
            </Link>
          )
        })}
      </nav>

      {/* User info */}
      <div className={cn(
        'border-t border-white/10 px-4 py-4',
        collapsed && 'px-2'
      )}>
        {!collapsed && session?.user && (
          <div>
            <div className="text-sm font-medium text-white truncate">{session.user.name}</div>
            <div className="text-xs text-sidebar-text/60 mt-0.5 truncate">{(session.user as any)?.role}</div>
          </div>
        )}
        {collapsed && (
          <div className="w-8 h-8 rounded-full bg-sidebar-active flex items-center justify-center text-white text-xs font-bold mx-auto">
            {session?.user?.name?.[0] ?? 'U'}
          </div>
        )}
      </div>
    </aside>
  )
}
