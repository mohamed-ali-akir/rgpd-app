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
        {/*
          Sur mobile : logo et déconnexion sur la 1re ligne, menu sur la 2e (défilement horizontal si besoin).
          À partir de "sm" (640 px) : tout sur une ligne. Les classes order-* changent l'ordre d'affichage
          sans changer l'ordre du HTML.
        */}
        <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-3 gap-y-2 px-4 py-3">
          <span className="order-1 font-bold text-indigo-700">Efface-moi</span>
          <nav
            className="order-3 -mx-1 flex w-full gap-1 overflow-x-auto px-1 sm:order-2 sm:mx-0 sm:w-auto sm:px-0"
            aria-label="Navigation principale"
          >
            {NAV.map((item) => (
              // NavLink sait si son lien correspond à la page affichée : on change alors le style.
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                className={({ isActive }) =>
                  `shrink-0 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap ${isActive ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100'}`
                }
              >
                {item.label}
              </NavLink>
            ))}
          </nav>
          <div className="order-2 flex items-center gap-2 text-sm text-slate-600 sm:order-3">
            <span className="hidden sm:inline">{user?.name}</span>
            <Button variant="ghost" loading={logout.isPending} onClick={() => logout.mutate(undefined, { onSuccess: () => navigate('/login') })}>
              Se déconnecter
            </Button>
          </div>
        </div>
      </header>

      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-6 sm:py-8">
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
