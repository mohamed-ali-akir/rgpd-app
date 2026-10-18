import type { CreateFromFindingsResult, Finding, FindingStatus, GuidedCheck, Scan } from '../types/api'
import { api } from './client'

export async function fetchScans(): Promise<Scan[]> {
  const { data } = await api.get<Scan[]>('/scans')
  return data
}

/** Lance une recherche (exécutée en arrière-plan : la réponse arrive avant la fin du scan). */
export async function startScan(): Promise<Scan> {
  const { data } = await api.post<Scan>('/scans')
  return data
}

export async function fetchFindings(): Promise<Finding[]> {
  const { data } = await api.get<Finding[]>('/findings')
  return data
}

export async function decideFinding(id: number, status: FindingStatus): Promise<Finding> {
  const { data } = await api.patch<Finding>(`/findings/${id}`, { status })
  return data
}

export async function createRequestsFromFindings(findingIds: number[]): Promise<CreateFromFindingsResult> {
  const { data } = await api.post<CreateFromFindingsResult>('/findings/requests', { finding_ids: findingIds })
  return data
}

export async function fetchGuidedChecks(): Promise<GuidedCheck[]> {
  const { data } = await api.get<GuidedCheck[]>('/guided-checks')
  return data
}
