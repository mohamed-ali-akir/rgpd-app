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
  | 'erasure_verified'
  | 'erasure_still_present'
  | 'verification_impossible'

/** Droit exercé (V2) : la V1 ne gérait que l'effacement. */
export type RequestKind = 'erasure' | 'access' | 'rectification' | 'restriction'

/** Vérification que les données ont disparu après un effacement accepté. */
export type VerificationStatus = 'pending' | 'cleared' | 'still_present' | 'unverifiable'

export type CheckOutcome = 'gone' | 'absent' | 'present' | 'unreachable' | 'blocked'

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
  | 'verify'
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
  auto_scan: boolean
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
  kind: RequestKind
  /** Correction demandée (rectification) ou motif (limitation). */
  details: string | null
  url: string
  status: RequestStatus
  channel: Channel
  data_types: DataType[]
  legal_grounds: LegalGround[]
  subject: string
  body: string
  sent_at: string | null
  due_at: string | null
  verification: VerificationStatus | null
  next_check_at: string | null
  created_at: string
  organization?: Organization
}

export interface ErasureCheck {
  id: number
  outcome: CheckOutcome
  failure: string | null
  http_status: number | null
  matched: DataType[]
  manual: boolean
  checked_at: string
}

/** Demande complète (page de détail) : historique et règles métier calculées par le back. */
export interface ErasureRequestDetail extends ErasureRequest {
  organization: Organization
  events: RequestEvent[]
  warnings: LegalWarning[]
  available_actions: RequestAction[]
  response_outcomes: ResponseOutcome[]
  reminder: { subject: string; body: string } | null
  checks: ErasureCheck[]
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

/* --- Scanner (V2) --- */

export type ScanStatus = 'queued' | 'running' | 'completed' | 'failed'

export type FindingSource = 'company_registry' | 'web_search'

export type FindingStatus = 'new' | 'confirmed' | 'rejected' | 'ignored'

export interface SourceReport {
  status: 'ok' | 'failed' | 'skipped'
  found?: number
  error?: string
}

export interface Scan {
  id: number
  trigger: 'manual' | 'scheduled'
  status: ScanStatus
  report: Partial<Record<FindingSource, SourceReport>>
  new_findings_count: number
  created_at: string
  started_at: string | null
  finished_at: string | null
}

export interface Finding {
  id: number
  source: FindingSource
  url: string
  domain: string
  title: string | null
  snippet: string | null
  meta: {
    verified?: boolean
    unverified_reason?: string
    siren?: string
    company?: string
    role?: string
  }
  matched: DataType[]
  confidence: number
  status: FindingStatus
  organization?: Organization
  erasure_request_id: number | null
  first_seen_at: string
  last_seen_at: string
}

export type SkipReason = 'not_found' | 'not_confirmed' | 'already_created' | 'duplicate_request' | 'unknown_organization'

export interface CreateFromFindingsResult {
  created: { finding_id: number; request_id: number }[]
  skipped: { finding_id: number; reason: SkipReason; request_id?: number }[]
}

export interface GuidedCheck {
  organization: Pick<Organization, 'id' | 'name' | 'domain' | 'category' | 'category_label'>
  search_url: string
}

/** GET /api/meta : informations publiques (mode démo). */
export interface Meta {
  demo: boolean
  demo_credentials: { email: string; password: string } | null
}
