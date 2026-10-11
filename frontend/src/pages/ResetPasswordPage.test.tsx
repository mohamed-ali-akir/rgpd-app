import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { AxiosError, AxiosHeaders } from 'axios'
import { Route, Routes, useLocation } from 'react-router'
import { describe, expect, it, vi } from 'vitest'
import * as authApi from '../api/auth'
import { renderWithProviders } from '../test/utils'
import { ResetPasswordPage } from './ResetPasswordPage'

vi.mock('../api/auth')

/** Page de connexion factice : affiche le message transmis par la navigation. */
function LoginStub() {
  const state = useLocation().state as { message?: string } | null
  return <p>Connexion : {state?.message}</p>
}

function renderPage(route = '/reset-password/jeton-123?email=camille%40exemple.test') {
  return renderWithProviders(
    <Routes>
      <Route path="/reset-password/:token" element={<ResetPasswordPage />} />
      <Route path="/login" element={<LoginStub />} />
    </Routes>,
    { route },
  )
}

async function fillAndSubmit(password: string, confirmation: string) {
  const user = userEvent.setup()
  await user.type(screen.getByLabelText('Nouveau mot de passe'), password)
  await user.type(screen.getByLabelText('Confirmer le mot de passe'), confirmation)
  await user.click(screen.getByRole('button', { name: 'Enregistrer le nouveau mot de passe' }))
}

describe('Réinitialisation du mot de passe', () => {
  it('refuse deux mots de passe différents sans appeler l\'API', async () => {
    renderPage()

    await fillAndSubmit('nouveau-mot-de-passe', 'autre-mot-de-passe')

    expect(await screen.findByText('Les mots de passe ne correspondent pas.')).toBeInTheDocument()
    expect(authApi.resetPassword).not.toHaveBeenCalled()
  })

  it('envoie le jeton et l\'adresse du lien, puis renvoie vers la connexion', async () => {
    vi.mocked(authApi.resetPassword).mockResolvedValue('Votre mot de passe a été modifié.')
    renderPage()

    await fillAndSubmit('nouveau-mot-de-passe', 'nouveau-mot-de-passe')

    expect(authApi.resetPassword).toHaveBeenCalledWith({
      token: 'jeton-123',
      email: 'camille@exemple.test',
      password: 'nouveau-mot-de-passe',
      password_confirmation: 'nouveau-mot-de-passe',
    })
    expect(await screen.findByText('Connexion : Votre mot de passe a été modifié.')).toBeInTheDocument()
  })

  it('affiche l\'erreur d\'un lien expiré', async () => {
    const message = 'Ce lien de réinitialisation est invalide ou a expiré. Demandez-en un nouveau.'
    vi.mocked(authApi.resetPassword).mockRejectedValue(
      new AxiosError('422', 'ERR_BAD_REQUEST', undefined, undefined, {
        status: 422,
        statusText: 'Unprocessable',
        headers: {},
        config: { headers: new AxiosHeaders() },
        data: { message, errors: { email: [message] } },
      }),
    )
    renderPage()

    await fillAndSubmit('nouveau-mot-de-passe', 'nouveau-mot-de-passe')

    expect(await screen.findByText(message)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Demander un nouveau lien' })).toBeInTheDocument()
  })

  it('signale un lien incomplet (adresse manquante)', () => {
    renderPage('/reset-password/jeton-123')

    expect(screen.getByText(/ce lien de réinitialisation est incomplet/i)).toBeInTheDocument()
  })
})
