import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../../api/auth'
import * as organizationsApi from '../../../api/organizations'
import * as requestsApi from '../../../api/requests'
import { makeOrganization, makeRequest, renderWithProviders } from '../../../test/utils'
import { NewRequestWizard } from './NewRequestWizard'

/*
 * vi.mock remplace les modules d'API par des fonctions factices : aucun appel HTTP réel.
 * On contrôle leurs réponses avec vi.mocked(...).mockResolvedValue(...).
 */
vi.mock('../../../api/organizations')
vi.mock('../../../api/requests')
vi.mock('../../../api/auth')

const organization = makeOrganization()

beforeEach(() => {
  vi.mocked(authApi.fetchUser).mockResolvedValue({ id: 1, name: 'Camille Martin', email: 'c@test', email_verified: true, google_email: null })
  vi.mocked(organizationsApi.resolveOrganization).mockResolvedValue({ domain: 'annuaire.test', organization })
  vi.mocked(organizationsApi.searchOrganizations).mockResolvedValue([])
})

/** Remplit l'étape 1 et passe à l'étape 2. */
async function goToOrganizationStep(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByLabelText(/adresse de la page/i), 'https://annuaire.test/camille')
  await user.click(screen.getByRole('button', { name: 'Suivant' }))
  await screen.findByText(/organisme détecté/i)
}

async function goToDataStep(user: ReturnType<typeof userEvent.setup>) {
  await goToOrganizationStep(user)
  await user.click(screen.getByRole('button', { name: 'Choisir cet organisme' }))
  await screen.findByText(/quelles données vous concernant/i)
}

describe('Assistant de création — étape 1 (URL)', () => {
  it('refuse une adresse invalide et reste sur l\'étape', async () => {
    const user = userEvent.setup()
    renderWithProviders(<NewRequestWizard />)

    await user.type(screen.getByLabelText(/adresse de la page/i), 'pas une adresse')
    await user.click(screen.getByRole('button', { name: 'Suivant' }))

    expect(await screen.findByText(/adresse invalide/i)).toBeInTheDocument()
    expect(organizationsApi.resolveOrganization).not.toHaveBeenCalled()
  })

  it('exige une adresse', async () => {
    const user = userEvent.setup()
    renderWithProviders(<NewRequestWizard />)

    await user.click(screen.getByRole('button', { name: 'Suivant' }))

    expect(await screen.findByText(/collez l'adresse de la page/i)).toBeInTheDocument()
  })

  it('identifie l\'organisme à partir de l\'URL', async () => {
    const user = userEvent.setup()
    renderWithProviders(<NewRequestWizard />)

    await goToOrganizationStep(user)

    expect(organizationsApi.resolveOrganization).toHaveBeenCalledWith('https://annuaire.test/camille')
    expect(screen.getByText('Annuaire Exemple')).toBeInTheDocument()
  })
})

describe('Assistant de création — étape 2 (organisme)', () => {
  it('propose de chercher ou créer l\'organisme s\'il est inconnu', async () => {
    vi.mocked(organizationsApi.resolveOrganization).mockResolvedValue({ domain: 'inconnu.fr', organization: null })
    const user = userEvent.setup()
    renderWithProviders(<NewRequestWizard />)

    await user.type(screen.getByLabelText(/adresse de la page/i), 'https://inconnu.fr/page')
    await user.click(screen.getByRole('button', { name: 'Suivant' }))

    expect(await screen.findByText(/aucun organisme connu pour/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Ajouter un nouvel organisme' })).toBeInTheDocument()
  })
})

describe('Assistant de création — étape 3 (données)', () => {
  it('exige au moins un type de donnée et la confirmation "mes propres données"', async () => {
    const user = userEvent.setup()
    renderWithProviders(<NewRequestWizard />)
    await goToDataStep(user)

    await user.click(screen.getByRole('button', { name: 'Générer la lettre' }))

    expect(await screen.findByText('Cochez au moins un type de donnée.')).toBeInTheDocument()
    expect(screen.getByText(/vous devez confirmer/i)).toBeInTheDocument()
    expect(requestsApi.createRequest).not.toHaveBeenCalled()
  })

  it('désactive Gmail quand l\'organisme n\'a pas d\'adresse e-mail', async () => {
    vi.mocked(organizationsApi.resolveOrganization).mockResolvedValue({
      domain: 'annuaire.test',
      organization: makeOrganization({ privacy_email: null }),
    })
    const user = userEvent.setup()
    renderWithProviders(<NewRequestWizard />)
    await goToDataStep(user)

    expect(screen.getByRole('radio', { name: /depuis mon adresse gmail/i })).toBeDisabled()
    expect(screen.getByRole('radio', { name: /je l'envoie moi-même/i })).toBeChecked()
  })

  it('crée le brouillon puis affiche la lettre à relire', async () => {
    // Le brouillon renvoyé à la création, puis relu par l'étape 4 (useRequest).
    const draft = makeRequest({ warnings: [{ code: 'legal_publication', message: 'Publication légale : un refus est probable.' }] })
    vi.mocked(requestsApi.createRequest).mockResolvedValue(draft)
    vi.mocked(requestsApi.fetchRequest).mockResolvedValue(draft)
    const user = userEvent.setup()
    renderWithProviders(<NewRequestWizard />)
    await goToDataStep(user)

    await user.click(screen.getByLabelText('Nom et prénom'))
    await user.click(screen.getByLabelText('Téléphone'))
    await user.click(screen.getByLabelText(/j'ai \(ou j'ai eu\) un compte/i))
    await user.click(screen.getByLabelText(/concerne mes propres données/i))
    await user.click(screen.getByRole('button', { name: 'Générer la lettre' }))

    expect(requestsApi.createRequest).toHaveBeenCalledWith({
      kind: 'erasure',
      url: 'https://annuaire.test/camille',
      organization_id: organization.id,
      data_types: ['name', 'phone'],
      self_published: false,
      has_account: true,
      channel: 'gmail',
      own_data_confirmed: true,
    })
    expect(await screen.findByLabelText('Texte')).toHaveValue('Corps de la lettre')
    expect(screen.getByText('Publication légale : un refus est probable.')).toBeInTheDocument()
  })
})

describe('Assistant de création — autres droits (V2)', () => {
  it('rectification : exige la correction demandée et masque l\'origine des données', async () => {
    const user = userEvent.setup()
    renderWithProviders(<NewRequestWizard />)
    await goToDataStep(user)

    await user.click(screen.getByRole('radio', { name: /rectification/i }))
    expect(screen.queryByLabelText(/j'ai publié ces informations/i)).not.toBeInTheDocument()

    await user.click(screen.getByLabelText('Nom et prénom'))
    await user.click(screen.getByLabelText(/concerne mes propres données/i))
    await user.click(screen.getByRole('button', { name: 'Générer la lettre' }))

    expect(await screen.findByText('Indiquez la correction demandée.')).toBeInTheDocument()
    expect(requestsApi.createRequest).not.toHaveBeenCalled()
  })

  it('rectification : envoie le type de droit et la correction', async () => {
    vi.mocked(requestsApi.createRequest).mockResolvedValue(makeRequest({ kind: 'rectification' }))
    vi.mocked(requestsApi.fetchRequest).mockResolvedValue(makeRequest({ kind: 'rectification' }))
    const user = userEvent.setup()
    renderWithProviders(<NewRequestWizard />)
    await goToDataStep(user)

    await user.click(screen.getByRole('radio', { name: /rectification/i }))
    await user.type(screen.getByLabelText('Correction demandée'), 'Remplacer 12 rue des Lilas par 14 rue des Lilas')
    await user.click(screen.getByLabelText('Adresse postale'))
    await user.click(screen.getByLabelText(/concerne mes propres données/i))
    await user.click(screen.getByRole('button', { name: 'Générer la lettre' }))

    expect(requestsApi.createRequest).toHaveBeenCalledWith(
      expect.objectContaining({ kind: 'rectification', details: 'Remplacer 12 rue des Lilas par 14 rue des Lilas', data_types: ['postal_address'] }),
    )
  })

  it('accès : les types de données sont facultatifs et aucun détail n\'est envoyé', async () => {
    vi.mocked(requestsApi.createRequest).mockResolvedValue(makeRequest({ kind: 'access' }))
    vi.mocked(requestsApi.fetchRequest).mockResolvedValue(makeRequest({ kind: 'access' }))
    const user = userEvent.setup()
    renderWithProviders(<NewRequestWizard />)
    await goToDataStep(user)

    await user.click(screen.getByRole('radio', { name: /accès à mes données/i }))
    await user.click(screen.getByLabelText(/concerne mes propres données/i))
    await user.click(screen.getByRole('button', { name: 'Générer la lettre' }))

    expect(requestsApi.createRequest).toHaveBeenCalledWith(expect.objectContaining({ kind: 'access', data_types: [], details: undefined }))
  })

  it('pré-remplit l\'adresse transmise par le scanner (?url=)', () => {
    renderWithProviders(<NewRequestWizard />, { route: '/requests/new?url=https%3A%2F%2Fannuaire.test%2Fcamille' })

    expect(screen.getByLabelText(/adresse de la page/i)).toHaveValue('https://annuaire.test/camille')
  })
})
