import type { ReactNode } from 'react'

type Tone = 'default' | 'warning' | 'danger'

/*
 * La couleur du cadre est choisie ici, et non passée par className : avec Tailwind,
 * deux classes "ring-slate-200" et "ring-amber-300" sur le même élément entrent en conflit
 * et c'est l'ordre dans la feuille CSS (pas dans l'attribut) qui décide laquelle gagne.
 */
const TONES: Record<Tone, string> = {
  default: 'ring-1 ring-slate-200',
  warning: 'ring-2 ring-amber-300',
  danger: 'ring-1 ring-red-200',
}

interface CardProps {
  title?: string
  actions?: ReactNode
  tone?: Tone
  children: ReactNode
  className?: string
}

export function Card({ title, actions, tone = 'default', children, className = '' }: CardProps) {
  return (
    <section className={`rounded-lg bg-white p-4 shadow-xs sm:p-5 ${TONES[tone]} ${className}`}>
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
