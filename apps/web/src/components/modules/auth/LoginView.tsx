'use client'

import { useState } from 'react'
import { signIn } from 'next-auth/react'
import { useRouter } from 'next/navigation'
import { useForm } from 'react-hook-form'
import { useLang } from '@/contexts/LangContext'
import { ChevronDown, ChevronUp, Leaf } from 'lucide-react'

interface LoginForm {
  email: string
  password: string
}

const TEST_ACCOUNTS = [
  { role: 'Admin', email: 'admin@esgchain.com', password: 'demo1234' },
  { role: 'Buyer', email: 'buyer@esgchain.com', password: 'demo1234' },
  { role: 'Sustain', email: 'sustain@esgchain.com', password: 'demo1234' },
  { role: 'Analyst', email: 'analyst@esgchain.com', password: 'demo1234' },
]

export function LoginView() {
  const { t } = useLang()
  const router = useRouter()
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [showTestAccounts, setShowTestAccounts] = useState(false)

  const { register, handleSubmit, setValue, formState: { errors } } = useForm<LoginForm>()

  const onSubmit = async (data: LoginForm) => {
    setLoading(true)
    setError('')
    try {
      const result = await signIn('credentials', {
        email: data.email,
        password: data.password,
        redirect: false,
      })
      if (result?.ok) {
        router.push('/dashboard')
      } else {
        setError(t('auth.loginError'))
      }
    } catch {
      setError(t('auth.loginError'))
    } finally {
      setLoading(false)
    }
  }

  const fillTestAccount = (email: string, password: string) => {
    setValue('email', email)
    setValue('password', password)
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-[#1a1714] via-[#1a4d3e] to-[#1a1714] flex items-center justify-center p-4">
      <div className="w-full max-w-md">
        {/* Logo card */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-accent mb-4">
            <Leaf size={28} className="text-white" />
          </div>
          <h1 className="font-heading text-3xl font-bold text-white tracking-tight">ESG·Chain</h1>
          <p className="text-white/60 text-sm mt-2">{t('auth.loginSubtitle')}</p>
        </div>

        {/* Login Card */}
        <div className="bg-surface rounded-card shadow-modal p-8">
          <h2 className="font-heading font-semibold text-text-primary text-lg mb-6">{t('auth.loginTitle')}</h2>

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <div>
              <label className="label">{t('common.email')}</label>
              <input
                type="email"
                className={`input ${errors.email ? 'border-red-400' : ''}`}
                placeholder={t('auth.emailPlaceholder')}
                {...register('email', { required: true })}
              />
            </div>
            <div>
              <label className="label">{t('common.password')}</label>
              <input
                type="password"
                className={`input ${errors.password ? 'border-red-400' : ''}`}
                placeholder={t('auth.passwordPlaceholder')}
                {...register('password', { required: true })}
              />
            </div>

            {error && (
              <div className="bg-red-50 border border-red-200 rounded-btn px-3 py-2 text-sm text-red-600">
                {error}
              </div>
            )}

            <button
              type="submit"
              disabled={loading}
              className="w-full btn-primary py-2.5 text-base font-semibold disabled:opacity-60 disabled:cursor-not-allowed"
            >
              {loading ? t('auth.loggingIn') : t('auth.loginButton')}
            </button>
          </form>

          {/* Test accounts */}
          <div className="mt-6 border-t border-border pt-4">
            <button
              type="button"
              onClick={() => setShowTestAccounts(!showTestAccounts)}
              className="flex items-center gap-2 text-xs text-text-secondary hover:text-text-primary transition-colors w-full"
            >
              {showTestAccounts ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
              {t('auth.testAccounts')}
            </button>

            {showTestAccounts && (
              <div className="mt-3 space-y-1.5">
                {TEST_ACCOUNTS.map(account => (
                  <button
                    key={account.email}
                    type="button"
                    onClick={() => fillTestAccount(account.email, account.password)}
                    className="w-full text-left px-3 py-2 rounded-btn hover:bg-surface-2 transition-colors"
                  >
                    <span className="text-xs font-medium text-accent">{account.role}</span>
                    <span className="text-xs text-text-secondary ml-2">{account.email}</span>
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>

        <p className="text-center text-white/40 text-xs mt-6">
          ESG·Chain &copy; {new Date().getFullYear()} · 永續供應鏈管理平台
        </p>
      </div>
    </div>
  )
}
