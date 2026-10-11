import type { ReactNode } from 'react'
import { LegalDisclaimer } from '../../components/LegalDisclaimer'

interface AuthLayoutProps {
  title: string
  children: ReactNode
  footer: ReactNode
}

/** Mise en page des écrans de connexion et d'inscription (sans menu). */
export function AuthLayout({ title, children, footer }: AuthLayoutProps) {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center px-4 py-12">
      <div className="w-full max-w-sm">
        <p className="text-center text-sm font-semibold tracking-wide text-indigo-600 uppercase">Efface-moi</p>
        <h1 className="mt-2 text-center text-2xl font-bold text-slate-900">{title}</h1>
        <p className="mt-2 text-center text-sm text-slate-600">
          Demandez la suppression de vos données personnelles publiées en ligne (RGPD, art. 17).
        </p>
        <div className="mt-8 rounded-lg bg-white p-6 shadow-xs ring-1 ring-slate-200">{children}</div>
        <p className="mt-6 text-center text-sm text-slate-600">{footer}</p>
        <div className="mt-8">
          <LegalDisclaimer compact />
        </div>
      </div>
    </div>
  )
}
