import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import type { ReactElement } from 'react'
import { MemoryRouter } from 'react-router'
import type { ErasureRequestDetail, Organization } from '../types/api'

/**
 * Rend un composant avec les mêmes "Providers" que l'application (cache + routeur),
 * mais un cache neuf à chaque test et sans nouvelle tentative en cas d'erreur.
 */
export function renderWithProviders(ui: ReactElement, { route = '/' } = {}) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[route]}>{ui}</MemoryRouter>
    </QueryClientProvider>,
  )
}

export function makeOrganization(overrides: Partial<Organization> = {}): Organization {
  return {
    id: 1,
    name: 'Annuaire Exemple',
    domain: 'annuaire.test',
    category: 'directory',
    category_label: 'Annuaire',
    privacy_email: 'dpo@annuaire.test',
    privacy_form_url: null,
    dpo_name: null,
    verified: true,
    user_created: false,
    ...overrides,
  }
}

export function makeRequest(overrides: Partial<ErasureRequestDetail> = {}): ErasureRequestDetail {
  return {
    id: 42,
    kind: 'erasure',
    details: null,
    url: 'https://annuaire.test/camille',
    status: 'draft',
    channel: 'gmail',
    data_types: ['name'],
    legal_grounds: ['art17_1_c'],
    subject: 'Objet',
    body: 'Corps de la lettre',
    sent_at: null,
    due_at: null,
    verification: null,
    next_check_at: null,
    created_at: '2026-10-10T08:00:00+00:00',
    organization: makeOrganization(),
    events: [],
    warnings: [],
    available_actions: ['edit', 'send', 'delete'],
    response_outcomes: [],
    reminder: null,
    checks: [],
    ...overrides,
  }
}
