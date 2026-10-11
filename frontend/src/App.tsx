import { Route, Routes } from 'react-router'
import { Layout } from './components/Layout'
import { GuestOnly, RequireAuth } from './features/auth/RouteGuards'
import { DashboardPage } from './pages/DashboardPage'
import { LoginPage } from './pages/LoginPage'
import { NewRequestPage } from './pages/NewRequestPage'
import { NotFoundPage } from './pages/NotFoundPage'
import { OrganizationsPage } from './pages/OrganizationsPage'
import { PrivacyPage } from './pages/PrivacyPage'
import { RegisterPage } from './pages/RegisterPage'
import { RequestDetailPage } from './pages/RequestDetailPage'
import { SettingsPage } from './pages/SettingsPage'

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
      </Route>

      <Route element={<RequireAuth />}>
        <Route element={<Layout />}>
          <Route index element={<DashboardPage />} />
          <Route path="/requests/new" element={<NewRequestPage />} />
          <Route path="/requests/:id" element={<RequestDetailPage />} />
          <Route path="/organizations" element={<OrganizationsPage />} />
          <Route path="/settings" element={<SettingsPage />} />
          <Route path="/privacy" element={<PrivacyPage />} />
        </Route>
      </Route>

      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  )
}
