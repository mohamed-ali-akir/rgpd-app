import { useEffect } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router'
import { useLogout, useResendVerification, useUser } from '../features/auth/useAuth'
import { LegalDisclaimer } from './LegalDisclaimer'
import { Alert } from './ui/Alert'
import { Button } from './ui/Button'

const NAV = [
  { to: '/', label: 'Tableau de bord', end: true },
  { to: '/requests/new', label: 'Nouvelle demande', end: false },
  { to: '/organizations', label: 'Organismes', end: false },
  { to: '/settings', label: 'Paramètres', end: false },
]

/** Mise en page des pages connectées : menu, bandeau de vérification d'e-mail, pied de page. */
export function Layout() {
  const { data: user } = useUser()
  const logout = useLogout()
  const resend = useResendVerification()
  const navigate = useNavigate()
  const { pathname } = useLocation()

  // À chaque changement de page, on revient en haut (le navigateur ne le fait pas seul dans un SPA).
  useEffect(() => {
    window.scrollTo(0, 0)
  }, [pathname])

  return (
    <div className="flex min-h-screen flex-col">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
          <span className="font-bold text-indigo-700">Efface-moi</span>
          <nav className="flex flex-wrap gap-1" aria-label="Navigation principale">
            {NAV.map((item) => (
              // NavLink sait si son lien correspond à la page affichée : on change alors le style.
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                className={({ isActive }) =>
                  `rounded-md px-3 py-1.5 text-sm font-medium ${isActive ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100'}`
                }
              >
                {item.label}
              </NavLink>
            ))}
          </nav>
          <div className="flex items-center gap-2 text-sm text-slate-600">
            <span className="hidden sm:inline">{user?.name}</span>
            <Button variant="ghost" loading={logout.isPending} onClick={() => logout.mutate(undefined, { onSuccess: () => navigate('/login') })}>
              Se déconnecter
            </Button>
          </div>
        </div>
      </header>

      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
        {user && !user.email_verified && (
          <div className="mb-6">
            <Alert tone="warning" title="Vérifiez votre adresse e-mail">
              Un lien vous a été envoyé à {user.email}. Sans vérification, l'envoi depuis Gmail est désactivé.{' '}
              {resend.isSuccess ? (
                <strong>Nouveau lien envoyé.</strong>
              ) : (
                <button type="button" className="font-semibold underline" disabled={resend.isPending} onClick={() => resend.mutate()}>
                  Renvoyer le lien
                </button>
              )}
            </Alert>
          </div>
        )}
        {/* Outlet : emplacement où React Router affiche la page de la route courante. */}
        <Outlet />
      </main>

      <footer className="border-t border-slate-200 bg-white">
        <div className="mx-auto flex max-w-5xl flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
          <LegalDisclaimer compact />
          <NavLink to="/privacy" className="shrink-0 text-xs font-medium text-indigo-600 hover:underline">
            Ce que nous faisons de vos données
          </NavLink>
        </div>
      </footer>
    </div>
  )
}
