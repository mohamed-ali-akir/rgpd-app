import { formatDateTime } from '../../lib/dates'
import type { EventType, RequestEvent } from '../../types/api'

const DOT_COLORS: Partial<Record<EventType, string>> = {
  sent: 'bg-blue-500',
  send_failed: 'bg-red-500',
  overdue: 'bg-amber-500',
  response_accepted: 'bg-green-500',
  response_refused: 'bg-red-500',
  complaint_generated: 'bg-purple-500',
}

/** Frise de l'historique de la demande (événements horodatés, jamais modifiés). */
export function Timeline({ events }: { events: RequestEvent[] }) {
  return (
    <ol className="relative space-y-4 border-l border-slate-200 pl-5">
      {events.map((event) => {
        const note = typeof event.payload.note === 'string' ? event.payload.note : null
        return (
          <li key={event.id} className="relative">
            <span className={`absolute top-1.5 -left-[25px] size-2.5 rounded-full ring-4 ring-white ${DOT_COLORS[event.type] ?? 'bg-slate-400'}`} aria-hidden />
            <p className="text-sm font-medium text-slate-900">{event.label}</p>
            <p className="text-xs text-slate-500">
              <time dateTime={event.occurred_at}>{formatDateTime(event.occurred_at)}</time>
              {event.payload.channel === 'manual' && ' · envoi par vos soins'}
              {event.payload.channel === 'gmail' && ' · via Gmail'}
            </p>
            {event.type === 'send_failed' && (
              <p className="text-xs text-red-700">
                {event.payload.reason === 'google_disconnected'
                  ? 'L\'accès à Gmail a été retiré : reconnectez Gmail dans les paramètres.'
                  : 'Gmail n\'a pas pu envoyer le message. Réessayez plus tard.'}
              </p>
            )}
            {note && <p className="mt-1 text-sm whitespace-pre-line text-slate-700">{note}</p>}
          </li>
        )
      })}
    </ol>
  )
}
