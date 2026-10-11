import type { ButtonHTMLAttributes } from 'react'
import { buttonClasses, type Variant } from './buttonClasses'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  loading?: boolean
}

/**
 * Un composant React est une fonction qui reçoit des "props" (ses paramètres)
 * et renvoie du JSX. "...rest" transmet toutes les autres props (onClick, type…) au <button>.
 */
export function Button({ variant = 'primary', loading = false, disabled, children, className = '', ...rest }: ButtonProps) {
  return (
    <button
      type="button"
      {...rest}
      disabled={disabled || loading}
      aria-busy={loading}
      className={`${buttonClasses(variant)} ${className}`}
    >
      {loading && <span className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden />}
      {children}
    </button>
  )
}
