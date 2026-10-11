import { Link } from 'react-router'
import { buttonClasses } from '../components/ui/buttonClasses'

export function NotFoundPage() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 px-4 text-center">
      <p className="text-sm font-semibold text-indigo-600">404</p>
      <h1 className="text-2xl font-bold text-slate-900">Page introuvable</h1>
      <Link to="/" className={buttonClasses('primary')}>
        Retour au tableau de bord
      </Link>
    </div>
  )
}
