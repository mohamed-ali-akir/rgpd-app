import type { Organization } from '../types/api'

/** Le canal Gmail n'est possible que si l'organisme a une adresse e-mail et n'est pas un moteur de recherche. */
export function gmailPossible(organization: Organization): boolean {
  return organization.privacy_email !== null && organization.category !== 'search_engine'
}
