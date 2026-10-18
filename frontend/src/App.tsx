import { lazy } from 'react'
import { Route, Routes } from 'react-router'
import { Layout } from './components/Layout'
import { GuestOnly, RequireAuth } from './features/auth/RouteGuards'
import { ForgotPasswordPage } from './pages/ForgotPasswordPage'
import { LoginPage } from './pages/LoginPage'
import { NotFoundPage } from './pages/NotFoundPage'
import { RegisterPage } from './pages/RegisterPage'
import { ResetPasswordPage } from './pages/ResetPasswordPage'

/*
 * Pages de l'espace connecté chargées à la demande (code splitting) : le visiteur qui arrive
 * sur la connexion ne télécharge pas l'assistant, le scanner, etc. Chaque page devient un
 * fichier JS séparé, téléchargé au premier affichage puis gardé en cache par le navigateur.
 * La Suspense qui affiche "Chargement…" pendant ce temps est dans Layout, autour de l'Outlet.
 */
const page = <K extends string>(load: () => Promise<Record<K, React.ComponentType>>, name: K) =>
  lazy(async () => ({ default: (await load())[name] }))

const DashboardPage = page(() => import('./pages/DashboardPage'), 'DashboardPage')
const NewRequestPage = page(() => import('./pages/NewRequestPage'), 'NewRequestPage')
const RequestDetailPage = page(() => import('./pages/RequestDetailPage'), 'RequestDetailPage')
const OrganizationsPage = page(() => import('./pages/OrganizationsPage'), 'OrganizationsPage')
const ScannerPage = page(() => import('./pages/ScannerPage'), 'ScannerPage')
const SettingsPage = page(() => import('./pages/SettingsPage'), 'SettingsPage')
const PrivacyPage = page(() => import('./pages/PrivacyPage'), 'PrivacyPage')

/**
 * Table des routes. Les routes "imbriquées" partagent un parent :
 * RequireAuth vérifie la connexion, puis Layout affiche le menu autour de la page.
 */
export function App() {
  return (
    <Routes>
      <Route element={<GuestOnly />}>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      </Route>

      {/* Accessible connecté ou non : le lien arrive par e-mail. */}
      <Route path="/reset-password/:token" element={<ResetPasswordPage />} />

      <Route element={<RequireAuth />}>
        <Route element={<Layout />}>
          <Route index element={<DashboardPage />} />
          <Route path="/requests/new" element={<NewRequestPage />} />
          <Route path="/requests/:id" element={<RequestDetailPage />} />
          <Route path="/organizations" element={<OrganizationsPage />} />
          <Route path="/scanner" element={<ScannerPage />} />
          <Route path="/settings" element={<SettingsPage />} />
          <Route path="/privacy" element={<PrivacyPage />} />
        </Route>
      </Route>

      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  )
}
