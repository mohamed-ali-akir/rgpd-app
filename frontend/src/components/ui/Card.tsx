import type { ReactNode } from 'react'

interface CardProps {
  title?: string
  actions?: ReactNode
  children: ReactNode
  className?: string
}

export function Card({ title, actions, children, className = '' }: CardProps) {
  return (
    <section className={`rounded-lg bg-white p-5 shadow-xs ring-1 ring-slate-200 ${className}`}>
      {(title || actions) && (
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          {title && <h2 className="text-base font-semibold text-slate-900">{title}</h2>}
          {actions}
        </div>
      )}
      {children}
    </section>
  )
}
