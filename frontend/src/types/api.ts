/*
 * Types qui reflètent les API Resources Laravel (backend/app/Http/Resources).
 * Si une Resource change côté back, ce fichier doit changer aussi :
 * TypeScript signalera alors tous les endroits du front à adapter.
 */

export type RequestStatus = 'draft' | 'sent' | 'extended' | 'accepted' | 'refused' | 'overdue' | 'complaint' | 'closed'

export type Channel = 'gmail' | 'manual'

export type DataType = 'name' | 'email' | 'phone' | 'postal_address' | 'photo' | 'birth_date' | 'employer' | 'other'

export type LegalGround = 'art17_1_a' | 'art17_1_b' | 'art17_1_c' | 'art17_1_d' | 'art17_1_e' | 'art17_1_f'

export type OrganizationCategory =
  | 'directory'
  | 'people_search'
  | 'social_network'
  | 'forum'
  | 'press'
  | 'legal_registry'
  | 'search_engine'
  | 'other'

export type EventType =
  | 'created'
  | 'sent'
  | 'send_failed'
  | 'reminder_sent'
  | 'extended'
  | 'response_accepted'
  | 'response_refused'
  | 'overdue'
  | 'complaint_generated'
  | 'closed'

/** Actions proposées par le back selon le statut (ErasureRequest::availableActions()). */
export type RequestAction =
  | 'edit'
  | 'send'
  | 'mark_sent'
  | 'reminder'
  | 'respond'
  | 'complaint'
  | 'close'
  | 'download_complaint'
  | 'delete'

export type ResponseOutcome = 'accepted' | 'refused' | 'extended'

export interface User {
  id: number
  name: string
  email: string
  email_verified: boolean
  google_email: string | null
}

export interface Profile {
  first_name: string
  last_name: string
  postal_address: string | null
  phone: string | null
}

export interface Organization {
  id: number
  name: string
  domain: string
  category: OrganizationCategory
  category_label: string
  privacy_email: string | null
  privacy_form_url: string | null
  dpo_name: string | null
  verified: boolean
  user_created: boolean
}

export interface ResolveResult {
  domain: string
  organization: Organization | null
}

export interface RequestEvent {
  id: number
  type: EventType
  label: string
  payload: Record<string, unknown>
  occurred_at: string
}

export interface LegalWarning {
  code: string
  message: string
}

/** Demande telle qu'elle apparaît dans une liste. */
export interface ErasureRequest {
  id: number
  url: string
  status: RequestStatus
  channel: Channel
  data_types: DataType[]
  legal_grounds: LegalGround[]
  subject: string
  body: string
  sent_at: string | null
  due_at: string | null
  created_at: string
  organization?: Organization
}

/** Demande complète (page de détail) : historique et règles métier calculées par le back. */
export interface ErasureRequestDetail extends ErasureRequest {
  organization: Organization
  events: RequestEvent[]
  warnings: LegalWarning[]
  available_actions: RequestAction[]
  response_outcomes: ResponseOutcome[]
  reminder: { subject: string; body: string } | null
}

export interface Dashboard {
  counts: Record<RequestStatus, number>
  overdue: ErasureRequest[]
}

/** Format des erreurs de validation Laravel (HTTP 422). */
export interface ValidationErrorBody {
  message: string
  errors?: Record<string, string[]>
}
