export function Spinner({ label = 'Chargement…' }: { label?: string }) {
  return (
    <div className="flex items-center gap-2 py-8 text-sm text-slate-500" role="status">
      <span className="size-5 animate-spin rounded-full border-2 border-indigo-600 border-t-transparent" aria-hidden />
      {label}
    </div>
  )
}
