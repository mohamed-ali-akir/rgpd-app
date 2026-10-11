import type { ReactNode } from 'react'

type Tone = 'info' | 'warning' | 'error' | 'success'

const TONES: Record<Tone, string> = {
  info: 'bg-blue-50 text-blue-900 ring-blue-200',
  warning: 'bg-amber-50 text-amber-900 ring-amber-200',
  error: 'bg-red-50 text-red-900 ring-red-200',
  success: 'bg-green-50 text-green-900 ring-green-200',
}

interface AlertProps {
  tone?: Tone
  title?: string
  children: ReactNode
}

export function Alert({ tone = 'info', title, children }: AlertProps) {
  return (
    // role="alert" pour les erreurs : le lecteur d'écran les annonce immédiatement.
    <div role={tone === 'error' ? 'alert' : 'status'} className={`rounded-md p-3 text-sm ring-1 ring-inset ${TONES[tone]}`}>
      {title && <p className="font-semibold">{title}</p>}
      <div className={title ? 'mt-1' : ''}>{children}</div>
    </div>
  )
}
