import { Navigate, Outlet, useLocation } from 'react-router'
import { Spinner } from '../../components/ui/Spinner'
import { useUser } from './useAuth'

/**
 * Protège les pages privées : pendant le chargement on attend, sans utilisateur
 * on redirige vers /login (en mémorisant la page demandée), sinon on affiche la page enfant (<Outlet />).
 */
export function RequireAuth() {
  const { data: user, isPending } = useUser()
  const location = useLocation()

  if (isPending) return <Spinner />
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />

  return <Outlet />
}

/** Pages de connexion / inscription : inutiles si on est déjà connecté. */
export function GuestOnly() {
  const { data: user, isPending } = useUser()

  if (isPending) return <Spinner />
  if (user) return <Navigate to="/" replace />

  return <Outlet />
}
