import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../api/auth'
import * as scannerApi from '../api/scanner'
import { makeOrganization, renderWithProviders } from '../test/utils'
import type { Finding, Scan } from '../types/api'
import { ScannerPage } from './ScannerPage'

vi.mock('../api/scanner')
vi.mock('../api/auth')

function makeFinding(overrides: Partial<Finding> = {}): Finding {
  return {
    id: 1,
    source: 'web_search',
    url: 'https://annuaire.test/camille',
    domain: 'annuaire.test',
    title: 'Camille Martin — Annuaire',
    snippet: 'Camille Martin, 06 12 34 56 78',
    meta: { verified: true },
    matched: ['name', 'phone'],
    confidence: 92,
    status: 'new',
    organization: makeOrganization(),
    erasure_request_id: null,
    first_seen_at: '2026-10-10T08:00:00+00:00',
    last_seen_at: '2026-10-10T08:00:00+00:00',
    ...overrides,
  }
}

const completedScan: Scan = {
  id: 7,
  trigger: 'manual',
  status: 'completed',
  report: { company_registry: { status: 'ok', found: 0 }, web_search: { status: 'skipped' } },
  new_findings_count: 1,
  created_at: '2026-10-10T08:00:00+00:00',
  started_at: '2026-10-10T08:00:01+00:00',
  finished_at: '2026-10-10T08:01:00+00:00',
}

beforeEach(() => {
  vi.mocked(authApi.fetchUser).mockResolvedValue({ id: 1, name: 'Camille Martin', email: 'c@test', email_verified: true, google_email: null })
  vi.mocked(scannerApi.fetchScans).mockResolvedValue([completedScan])
  vi.mocked(scannerApi.fetchGuidedChecks).mockResolvedValue([])
})

describe('Page « Mes données en ligne »', () => {
  it('affiche le rapport par source de la dernière recherche', async () => {
    vi.mocked(scannerApi.fetchFindings).mockResolvedValue([])
    renderWithProviders(<ScannerPage />)

    expect(await screen.findByText(/1 nouveau\(x\) résultat\(s\)/)).toBeInTheDocument()
    expect(screen.getByText(/non configurée/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Relancer une recherche' })).toBeInTheDocument()
  })

  it('indique la recherche en cours au lieu du bouton', async () => {
    vi.mocked(scannerApi.fetchScans).mockResolvedValue([{ ...completedScan, status: 'running', finished_at: null }])
    vi.mocked(scannerApi.fetchFindings).mockResolvedValue([])
    renderWithProviders(<ScannerPage />)

    expect(await screen.findByRole('status')).toHaveTextContent(/recherche en cours/i)
    expect(screen.queryByRole('button', { name: /lancer/i })).not.toBeInTheDocument()
  })

  it('« C\'est moi » envoie la décision et déplace le résultat dans les confirmés', async () => {
    const finding = makeFinding()
    vi.mocked(scannerApi.fetchFindings).mockResolvedValueOnce([finding]).mockResolvedValue([{ ...finding, status: 'confirmed' }])
    vi.mocked(scannerApi.decideFinding).mockResolvedValue({ ...finding, status: 'confirmed' })
    const user = userEvent.setup()
    renderWithProviders(<ScannerPage />)

    await user.click(await screen.findByRole('button', { name: "C'est moi" }))

    expect(scannerApi.decideFinding).toHaveBeenCalledWith(1, 'confirmed')
    expect(await screen.findByText('Confirmés (1)')).toBeInTheDocument()
    expect(screen.queryByText(/à examiner/i)).not.toBeInTheDocument()
  })

  it('revient à l\'état précédent si le serveur refuse la décision', async () => {
    const finding = makeFinding()
    vi.mocked(scannerApi.fetchFindings).mockResolvedValue([finding])
    vi.mocked(scannerApi.decideFinding).mockRejectedValue(new Error('refus'))
    const user = userEvent.setup()
    renderWithProviders(<ScannerPage />)

    await user.click(await screen.findByRole('button', { name: "C'est moi" }))

    expect(await screen.findByText('À examiner (1)')).toBeInTheDocument()
    expect(screen.queryByText(/confirmés/i)).not.toBeInTheDocument()
  })

  it('prépare les demandes des résultats confirmés et explique ceux ignorés', async () => {
    const ready = makeFinding({ id: 1, status: 'confirmed' })
    const unknown = makeFinding({ id: 2, status: 'confirmed', domain: 'inconnu.fr', title: 'Page inconnue', organization: undefined })
    vi.mocked(scannerApi.fetchFindings).mockResolvedValue([ready, unknown])
    vi.mocked(scannerApi.createRequestsFromFindings).mockResolvedValue({
      created: [{ finding_id: 1, request_id: 42 }],
      skipped: [{ finding_id: 2, reason: 'unknown_organization' }],
    })
    const user = userEvent.setup()
    renderWithProviders(<ScannerPage />)

    // Le résultat sans organisme propose aussi la création manuelle, avec l'URL pré-remplie.
    const unknownCard = await screen.findByRole('article', { name: 'Page inconnue' })
    expect(within(unknownCard).getByRole('link', { name: /créer la demande à la main/i })).toHaveAttribute(
      'href',
      '/requests/new?url=https%3A%2F%2Fannuaire.test%2Fcamille',
    )

    await user.click(screen.getByRole('button', { name: "Préparer les 2 demandes d'effacement" }))

    expect(scannerApi.createRequestsFromFindings).toHaveBeenCalledWith([1, 2])
    expect(await screen.findByText(/1 brouillon\(s\) créé\(s\)/)).toBeInTheDocument()
    expect(screen.getByText(/inconnu\.fr : organisme inconnu/)).toBeInTheDocument()
  })

  it('un résultat déjà transformé en demande renvoie vers celle-ci', async () => {
    vi.mocked(scannerApi.fetchFindings).mockResolvedValue([makeFinding({ status: 'confirmed', erasure_request_id: 42 })])
    renderWithProviders(<ScannerPage />)

    expect(await screen.findByRole('link', { name: 'Voir la demande créée' })).toHaveAttribute('href', '/requests/42')
    expect(screen.queryByRole('button', { name: /préparer/i })).not.toBeInTheDocument()
  })
})
