import type { Organization, OrganizationCategory, ResolveResult } from '../types/api'
import { api } from './client'

export interface OrganizationInput {
  name: string
  domain: string
  category: OrganizationCategory
  privacy_email?: string | null
  privacy_form_url?: string | null
  dpo_name?: string | null
}

export async function searchOrganizations(search: string): Promise<Organization[]> {
  const { data } = await api.get<Organization[]>('/organizations', { params: { search } })
  return data
}

/** URL collée par l'utilisateur → domaine + organisme connu (ou null). */
export async function resolveOrganization(url: string): Promise<ResolveResult> {
  const { data } = await api.get<ResolveResult>('/organizations/resolve', { params: { url } })
  return data
}

export async function createOrganization(input: OrganizationInput): Promise<Organization> {
  const { data } = await api.post<Organization>('/organizations', input)
  return data
}
