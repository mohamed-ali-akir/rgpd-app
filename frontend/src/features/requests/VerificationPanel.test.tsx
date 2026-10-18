import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import * as requestsApi from '../../api/requests'
import { makeRequest, renderWithProviders } from '../../test/utils'
import { VerificationPanel } from './VerificationPanel'

vi.mock('../../api/requests')

const accepted = { status: 'accepted' as const, available_actions: ['verify' as const] }

describe('Vérification de l\'effacement', () => {
  it('n\'apparaît pas tant qu\'aucune vérification n\'est prévue', () => {
    const { container } = renderWithProviders(<VerificationPanel request={makeRequest()} onStarted={vi.fn()} waiting={false} />)

    expect(container).toBeEmptyDOMElement()
  })

  it('données toujours en ligne : alerte et historique des contrôles', () => {
    const request = makeRequest({
      ...accepted,
      verification: 'still_present',
      checks: [{ id: 1, outcome: 'present', failure: null, http_status: 200, matched: ['phone'], manual: false, checked_at: '2026-10-15T07:00:00+00:00' }],
    })
    renderWithProviders(<VerificationPanel request={request} onStarted={vi.fn()} waiting={false} />)

    expect(screen.getByText('Données toujours en ligne')).toBeInTheDocument()
    expect(screen.getByText('Données toujours présentes')).toBeInTheDocument()
    expect(screen.getByText(/Téléphone/)).toBeInTheDocument()
  })

  it('« Vérifier maintenant » lance la vérification et prévient la page', async () => {
    const request = makeRequest({ ...accepted, verification: 'pending' })
    vi.mocked(requestsApi.verifyRequest).mockResolvedValue(request)
    const onStarted = vi.fn()
    const user = userEvent.setup()
    renderWithProviders(<VerificationPanel request={request} onStarted={onStarted} waiting={false} />)

    await user.click(screen.getByRole('button', { name: 'Vérifier maintenant' }))

    expect(requestsApi.verifyRequest).toHaveBeenCalledWith(42)
    expect(onStarted).toHaveBeenCalledWith(0)
  })

  it('pas de bouton quand le serveur ne propose pas l\'action (délai entre deux vérifications)', () => {
    renderWithProviders(<VerificationPanel request={makeRequest({ status: 'accepted', available_actions: [], verification: 'cleared' })} onStarted={vi.fn()} waiting={false} />)

    expect(screen.getByText('Effacement vérifié')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Vérifier maintenant' })).not.toBeInTheDocument()
  })
})
