import type {
  Channel,
  Dashboard,
  DataType,
  ErasureRequest,
  ErasureRequestDetail,
  LegalGround,
  RequestKind,
  RequestStatus,
  ResponseOutcome,
} from '../types/api'
import { api } from './client'

export interface CreateRequestInput {
  url: string
  organization_id: number
  kind: RequestKind
  details?: string
  data_types: DataType[]
  self_published: boolean
  has_account: boolean
  channel: Channel
  own_data_confirmed: boolean
}

export interface UpdateRequestInput {
  subject?: string
  body?: string
  data_types?: DataType[]
  legal_grounds?: LegalGround[]
  details?: string
  channel?: Channel
  regenerate_letter?: boolean
}

export async function fetchDashboard(): Promise<Dashboard> {
  const { data } = await api.get<Dashboard>('/dashboard')
  return data
}

export async function fetchRequests(status?: RequestStatus): Promise<ErasureRequest[]> {
  const { data } = await api.get<ErasureRequest[]>('/requests', { params: status ? { status } : {} })
  return data
}

export async function fetchRequest(id: number): Promise<ErasureRequestDetail> {
  const { data } = await api.get<ErasureRequestDetail>(`/requests/${id}`)
  return data
}

export async function createRequest(input: CreateRequestInput): Promise<ErasureRequestDetail> {
  const { data } = await api.post<ErasureRequestDetail>('/requests', input)
  return data
}

export async function updateRequest(id: number, input: UpdateRequestInput): Promise<ErasureRequestDetail> {
  const { data } = await api.patch<ErasureRequestDetail>(`/requests/${id}`, input)
  return data
}

export async function deleteRequest(id: number): Promise<void> {
  await api.delete(`/requests/${id}`)
}

/*
 * Actions de la machine à états : chacune renvoie la demande à jour.
 */

export async function sendRequest(id: number): Promise<ErasureRequestDetail> {
  const { data } = await api.post<ErasureRequestDetail>(`/requests/${id}/send`)
  return data
}

export async function markSent(id: number, sentAt?: string): Promise<ErasureRequestDetail> {
  const { data } = await api.post<ErasureRequestDetail>(`/requests/${id}/mark-sent`, { sent_at: sentAt || null })
  return data
}

export async function sendReminder(id: number): Promise<ErasureRequestDetail> {
  const { data } = await api.post<ErasureRequestDetail>(`/requests/${id}/reminder`)
  return data
}

export async function recordResponse(id: number, outcome: ResponseOutcome, note: string): Promise<ErasureRequestDetail> {
  const { data } = await api.post<ErasureRequestDetail>(`/requests/${id}/responses`, { outcome, note: note || null })
  return data
}

export async function prepareComplaint(id: number): Promise<ErasureRequestDetail> {
  const { data } = await api.post<ErasureRequestDetail>(`/requests/${id}/complaint`)
  return data
}

export async function closeRequest(id: number): Promise<ErasureRequestDetail> {
  const { data } = await api.post<ErasureRequestDetail>(`/requests/${id}/close`)
  return data
}

/** Vérification immédiate de l'effacement (en arrière-plan : la page est relue par le serveur). */
export async function verifyRequest(id: number): Promise<ErasureRequestDetail> {
  const { data } = await api.post<ErasureRequestDetail>(`/requests/${id}/verify`)
  return data
}

/** Lien direct (téléchargement par le navigateur, pas par axios). */
export function complaintPdfUrl(id: number): string {
  return `/api/requests/${id}/complaint.pdf`
}
