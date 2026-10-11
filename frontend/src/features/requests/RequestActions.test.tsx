import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import * as requestsApi from '../../api/requests'
import { makeRequest, renderWithProviders } from '../../test/utils'
import type { RequestAction } from '../../types/api'
import { RequestActions } from './RequestActions'

vi.mock('../../api/requests', async (importOriginal) => ({
  // On garde complaintPdfUrl (simple fonction) et on remplace les appels HTTP.
  ...(await importOriginal<typeof requestsApi>()),
  recordResponse: vi.fn(),
  sendReminder: vi.fn(),
  prepareComplaint: vi.fn(),
  closeRequest: vi.fn(),
  deleteRequest: vi.fn(),
}))

function renderActions(actions: RequestAction[], overrides = {}) {
  return renderWithProviders(<RequestActions request={makeRequest({ status: 'sent', available_actions: actions, ...overrides })} />)
}

describe('Actions de la page de détail selon le statut', () => {
  it('demande envoyée : saisie de la réponse, sans relance ni plainte', () => {
    renderActions(['respond', 'delete'], { response_outcomes: ['extended', 'accepted', 'refused'] })

    expect(screen.getByRole('heading', { name: 'J\'ai reçu une réponse' })).toBeInTheDocument()
    expect(screen.queryByText('Relancer l\'organisme')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Préparer la plainte CNIL' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Supprimer la demande' })).toBeInTheDocument()
  })

  it('ne propose que les réponses autorisées par le back', () => {
    renderActions(['respond', 'delete'], { response_outcomes: ['accepted', 'refused'] })

    const options = screen.getAllByRole('option').map((o) => o.getAttribute('value'))
    expect(options).toEqual(['accepted', 'refused'])
  })

  it('délai dépassé (canal manuel) : affiche le texte de relance à copier', () => {
    renderActions(['reminder', 'respond', 'complaint', 'delete'], {
      status: 'overdue',
      channel: 'manual',
      response_outcomes: ['accepted', 'refused'],
      reminder: { subject: 'Relance', body: 'Texte de relance' },
    })

    expect(screen.getByRole('textbox', { name: /texte de la relance/i })).toHaveValue('Texte de relance')
    expect(screen.getByRole('button', { name: 'J\'ai envoyé la relance' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Préparer la plainte CNIL' })).toBeInTheDocument()
  })

  it('délai dépassé (canal Gmail) : bouton d\'envoi de la relance', async () => {
    vi.mocked(requestsApi.sendReminder).mockResolvedValue(makeRequest({ status: 'overdue' }))
    const user = userEvent.setup()
    renderActions(['reminder', 'delete'], { status: 'overdue', channel: 'gmail' })

    await user.click(screen.getByRole('button', { name: 'Envoyer la relance depuis Gmail' }))

    expect(requestsApi.sendReminder).toHaveBeenCalledWith(42)
  })

  it('plainte préparée : lien de téléchargement du PDF et clôture', () => {
    renderActions(['close', 'download_complaint', 'delete'], { status: 'complaint' })

    expect(screen.getByRole('link', { name: 'Télécharger le dossier PDF' })).toHaveAttribute('href', '/api/requests/42/complaint.pdf')
    expect(screen.getByRole('button', { name: 'Clôturer la demande' })).toBeInTheDocument()
  })

  it('enregistre la réponse saisie', async () => {
    vi.mocked(requestsApi.recordResponse).mockResolvedValue(makeRequest({ status: 'refused' }))
    const user = userEvent.setup()
    renderActions(['respond', 'delete'], { response_outcomes: ['accepted', 'refused'] })

    await user.selectOptions(screen.getByLabelText('Réponse reçue'), 'refused')
    await user.type(screen.getByLabelText('Note (facultatif)'), 'Refus sans motif')
    await user.click(screen.getByRole('button', { name: 'Enregistrer la réponse' }))

    expect(requestsApi.recordResponse).toHaveBeenCalledWith(42, 'refused', 'Refus sans motif')
  })

  it('demande la confirmation avant de supprimer', async () => {
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false)
    const user = userEvent.setup()
    renderActions(['delete'])

    await user.click(screen.getByRole('button', { name: 'Supprimer la demande' }))

    expect(confirm).toHaveBeenCalled()
    expect(requestsApi.deleteRequest).not.toHaveBeenCalled()
  })
})
