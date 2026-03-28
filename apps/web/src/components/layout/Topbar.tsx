'use client'

import { useSession, signOut } from 'next-auth/react'
import { Bell, Globe, ChevronDown, LogOut } from 'lucide-react'
import { useState } from 'react'
import { useLang } from '@/contexts/LangContext'

export function Topbar() {
  const { data: session } = useSession()
  const { lang, setLang, t } = useLang()
  const [showUserMenu, setShowUserMenu] = useState(false)

  return (
    <header className="h-14 border-b border-border bg-surface flex items-center justify-between px-6 shrink-0">
      <div className="text-sm text-text-secondary">
        {/* breadcrumb placeholder */}
      </div>

      <div className="flex items-center gap-3">
        {/* Language toggle */}
        <button
          onClick={() => setLang(lang === 'zh' ? 'en' : 'zh')}
          className="flex items-center gap-1.5 text-sm text-text-secondary hover:text-text-primary px-2 py-1.5 rounded hover:bg-surface-2 transition-colors"
        >
          <Globe size={15} />
          <span className="font-mono text-xs">{lang.toUpperCase()}</span>
        </button>

        {/* Notifications */}
        <button className="relative p-2 rounded hover:bg-surface-2 text-text-secondary hover:text-text-primary transition-colors">
          <Bell size={18} />
          <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full" />
        </button>

        {/* User menu */}
        <div className="relative">
          <button
            onClick={() => setShowUserMenu(!showUserMenu)}
            className="flex items-center gap-2 text-sm text-text-primary hover:bg-surface-2 px-3 py-1.5 rounded transition-colors"
          >
            <div className="w-7 h-7 rounded-full bg-accent flex items-center justify-center text-white text-xs font-bold">
              {session?.user?.name?.[0] ?? 'U'}
            </div>
            <span className="font-medium">{session?.user?.name ?? '—'}</span>
            <ChevronDown size={14} className="text-text-secondary" />
          </button>

          {showUserMenu && (
            <div className="absolute right-0 top-full mt-1 w-44 bg-surface border border-border rounded-card shadow-modal z-50">
              <button
                onClick={() => signOut({ callbackUrl: '/login' })}
                className="flex items-center gap-2 w-full px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors rounded-card"
              >
                <LogOut size={15} />
                {t('common.logout')}
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  )
}
