import type { Config } from 'tailwindcss'

const config: Config = {
  content: [
    './src/pages/**/*.{js,ts,jsx,tsx,mdx}',
    './src/components/**/*.{js,ts,jsx,tsx,mdx}',
    './src/app/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      colors: {
        bg: '#f5f3ee',
        surface: '#ffffff',
        'surface-2': '#f0ede6',
        border: '#e2ddd6',
        'text-primary': '#1c1917',
        'text-secondary': '#78716c',
        accent: '#1a4d3e',
        'accent-light': '#d1fae5',
        'accent-hover': '#153d31',
        'sidebar-bg': '#1a1714',
        'sidebar-text': '#d6d3d1',
        'sidebar-active': '#1a4d3e',
        risk: {
          low: '#16a34a',
          medium: '#d97706',
          high: '#dc2626',
          critical: '#7c3aed',
        },
        status: {
          draft: '#78716c',
          active: '#16a34a',
          pending: '#d97706',
          suspended: '#dc2626',
        },
      },
      fontFamily: {
        sans: ['Noto Sans TC', 'system-ui', 'sans-serif'],
        heading: ['Syne', 'system-ui', 'sans-serif'],
        mono: ['Fira Code', 'monospace'],
      },
      borderRadius: {
        card: '12px',
        btn: '8px',
      },
      boxShadow: {
        card: '0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04)',
        'card-hover': '0 4px 12px rgba(0,0,0,0.08)',
        modal: '0 20px 60px rgba(0,0,0,0.15)',
      },
    },
  },
  plugins: [],
}

export default config
