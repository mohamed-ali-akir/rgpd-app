const dateFormatter = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' })
const dateTimeFormatter = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeStyle: 'short' })

/** "2026-10-10T08:00:00+00:00" → "10 octobre 2026" */
export function formatDate(iso: string | null): string {
  return iso ? dateFormatter.format(new Date(iso)) : '—'
}

export function formatDateTime(iso: string): string {
  return dateTimeFormatter.format(new Date(iso))
}

const DAY_MS = 24 * 60 * 60 * 1000

/** Nombre de jours (arrondi au supérieur) avant l'échéance ; négatif si elle est dépassée. */
export function daysUntil(iso: string, now: Date = new Date()): number {
  return Math.ceil((new Date(iso).getTime() - now.getTime()) / DAY_MS)
}

export function deadlineText(iso: string | null, now: Date = new Date()): string | null {
  if (!iso) return null
  const days = daysUntil(iso, now)
  if (days > 1) return `${days} jours restants`
  if (days === 1) return 'Dernier jour'
  if (days === 0) return 'Échéance aujourd\'hui'
  return `Échéance dépassée de ${-days} jour${days < -1 ? 's' : ''}`
}

/** Date du jour au format AAAA-MM-JJ (valeur d'un <input type="date">). */
export function todayIsoDate(now: Date = new Date()): string {
  const offset = now.getTimezoneOffset() * 60_000
  return new Date(now.getTime() - offset).toISOString().slice(0, 10)
}
