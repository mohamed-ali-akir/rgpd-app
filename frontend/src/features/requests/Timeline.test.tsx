import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import type { RequestEvent } from '../../types/api'
import { Timeline } from './Timeline'

function sendFailed(reason: string): RequestEvent {
  return { id: 1, type: 'send_failed', label: 'Échec de l\'envoi', payload: { kind: 'initial', reason }, occurred_at: '2026-10-18T09:00:00+00:00' }
}

describe('Historique : échecs d\'envoi', () => {
  it('envoi interrompu : demande de vérifier Gmail avant de renvoyer', () => {
    render(<Timeline events={[sendFailed('uncertain')]} />)

    expect(screen.getByText(/Vérifiez vos messages envoyés dans Gmail avant de réessayer/)).toBeInTheDocument()
  })

  it('accès Gmail retiré : demande de reconnecter Gmail', () => {
    render(<Timeline events={[sendFailed('google_disconnected')]} />)

    expect(screen.getByText(/reconnectez Gmail/)).toBeInTheDocument()
  })

  it('raison inconnue : message d\'erreur Gmail générique', () => {
    render(<Timeline events={[sendFailed('autre')]} />)

    expect(screen.getByText('Gmail n\'a pas pu envoyer le message. Réessayez plus tard.')).toBeInTheDocument()
  })
})
