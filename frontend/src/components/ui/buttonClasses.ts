export type Variant = 'primary' | 'secondary' | 'danger' | 'ghost'

const VARIANTS: Record<Variant, string> = {
  primary: 'bg-indigo-600 text-white hover:bg-indigo-700 focus-visible:outline-indigo-600',
  secondary: 'bg-white text-slate-800 ring-1 ring-slate-300 ring-inset hover:bg-slate-50',
  danger: 'bg-red-600 text-white hover:bg-red-700 focus-visible:outline-red-600',
  ghost: 'text-slate-700 hover:bg-slate-100',
}

/** Classes partagées avec les liens qui ressemblent à des boutons. */
export function buttonClasses(variant: Variant = 'primary'): string {
  return `inline-flex items-center justify-center gap-2 rounded-md px-3.5 py-2 text-sm font-semibold shadow-xs focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${VARIANTS[variant]}`
}
