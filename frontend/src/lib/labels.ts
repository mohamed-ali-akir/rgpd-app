import type { DataType, LegalGround, OrganizationCategory, RequestStatus } from '../types/api'

/*
 * Libellés affichés à l'écran. Record<Type, string> oblige à donner un libellé
 * pour CHAQUE valeur : si on ajoute un statut au type, TypeScript signale l'oubli ici.
 */

export const STATUS_LABELS: Record<RequestStatus, string> = {
  draft: 'Brouillon',
  sent: 'Envoyée',
  extended: 'Délai prolongé',
  accepted: 'Acceptée',
  refused: 'Refusée',
  overdue: 'Délai dépassé',
  complaint: 'Plainte CNIL',
  closed: 'Clôturée',
}

/** Classes Tailwind du badge de chaque statut. */
export const STATUS_COLORS: Record<RequestStatus, string> = {
  draft: 'bg-slate-100 text-slate-700',
  sent: 'bg-blue-100 text-blue-800',
  extended: 'bg-indigo-100 text-indigo-800',
  accepted: 'bg-green-100 text-green-800',
  refused: 'bg-red-100 text-red-800',
  overdue: 'bg-amber-100 text-amber-900',
  complaint: 'bg-purple-100 text-purple-800',
  closed: 'bg-slate-200 text-slate-600',
}

export const STATUSES = Object.keys(STATUS_LABELS) as RequestStatus[]

export const DATA_TYPE_LABELS: Record<DataType, string> = {
  name: 'Nom et prénom',
  email: 'Adresse e-mail',
  phone: 'Téléphone',
  postal_address: 'Adresse postale',
  photo: 'Photo',
  birth_date: 'Date de naissance',
  employer: 'Employeur',
  other: 'Autre',
}

export const DATA_TYPES = Object.keys(DATA_TYPE_LABELS) as DataType[]

export const LEGAL_GROUND_LABELS: Record<LegalGround, string> = {
  art17_1_a: 'Art. 17.1.a — données plus nécessaires',
  art17_1_b: 'Art. 17.1.b — retrait du consentement',
  art17_1_c: 'Art. 17.1.c — opposition (art. 21)',
  art17_1_d: 'Art. 17.1.d — traitement illicite',
  art17_1_e: 'Art. 17.1.e — obligation légale d\'effacement',
  art17_1_f: 'Art. 17.1.f — données collectées quand j\'étais mineur',
}

export const LEGAL_GROUNDS = Object.keys(LEGAL_GROUND_LABELS) as LegalGround[]

export const CATEGORY_LABELS: Record<OrganizationCategory, string> = {
  directory: 'Annuaire',
  people_search: 'Recherche de personnes',
  social_network: 'Réseau social',
  forum: 'Forum',
  press: 'Presse',
  legal_registry: 'Registre légal',
  search_engine: 'Moteur de recherche',
  other: 'Autre',
}

export const CATEGORIES = Object.keys(CATEGORY_LABELS) as OrganizationCategory[]
