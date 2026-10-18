import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../api/auth'
import * as metaApi from '../api/meta'
import { renderWithProviders } from '../test/utils'
import { LoginPage } from './LoginPage'

vi.mock('../api/auth')
vi.mock('../api/meta')

beforeEach(() => {
  vi.mocked(authApi.fetchUser).mockResolvedValue(null)
})

describe('Connexion et mode démo', () => {
  it('hors démo : liens d\'inscription et de mot de passe oublié, pas de bandeau', async () => {
    vi.mocked(metaApi.fetchMeta).mockResolvedValue({ demo: false, demo_credentials: null })
    renderWithProviders(<LoginPage />, { route: '/login' })

    expect(await screen.findByRole('link', { name: 'Créer un compte' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Mot de passe oublié ?' })).toBeInTheDocument()
    expect(screen.queryByRole('note')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Essayer la démo' })).not.toBeInTheDocument()
  })

  it('en démo : bandeau, identifiants affichés et connexion en un clic', async () => {
    const credentials = { email: 'demo@example.test', password: 'demo-efface-moi' }
    vi.mocked(metaApi.fetchMeta).mockResolvedValue({ demo: true, demo_credentials: credentials })
    vi.mocked(authApi.login).mockResolvedValue({ id: 1, name: 'Camille Démo', email: credentials.email, email_verified: true, google_email: null })
    const user = userEvent.setup()
    renderWithProviders(<LoginPage />, { route: '/login' })

    await user.click(await screen.findByRole('button', { name: 'Essayer la démo' }))

    expect(authApi.login).toHaveBeenCalledWith(credentials)
    expect(screen.getByRole('note')).toHaveTextContent(/démonstration/i)
    expect(screen.queryByRole('link', { name: 'Créer un compte' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Mot de passe oublié ?' })).not.toBeInTheDocument()
  })
})
