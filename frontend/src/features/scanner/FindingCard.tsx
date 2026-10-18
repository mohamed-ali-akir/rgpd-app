import { Link } from 'react-router'
import { Button } from '../../components/ui/Button'
import { DATA_TYPE_LABELS, FINDING_SOURCE_LABELS } from '../../lib/labels'
import type { Finding, FindingStatus } from '../../types/api'

interface FindingCardProps {
  finding: Finding
  onDecide: (status: FindingStatus) => void
  busy: boolean
}

function confidenceLabel(confidence: number): { text: string; className: string } {
  if (confidence >= 80) return { text: 'Très probable', className: 'bg-green-100 text-green-800' }
  if (confidence >= 50) return { text: 'Probable', className: 'bg-amber-100 text-amber-900' }
  return { text: 'Possible (homonyme ?)', className: 'bg-slate-100 text-slate-700' }
}

/** Un résultat du scanner, avec les décisions possibles. */
export function FindingCard({ finding, onDecide, busy }: FindingCardProps) {
  const confidence = confidenceLabel(finding.confidence)

  return (
    <article className="rounded-md p-4 ring-1 ring-slate-200" aria-label={finding.title ?? finding.domain}>
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="font-medium text-slate-900">{finding.title ?? finding.domain}</p>
          <a href={finding.url} target="_blank" rel="noreferrer" className="block truncate text-xs text-indigo-600 underline">
            {finding.url}
          </a>
        </div>
        <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${confidence.className}`}>
          {confidence.text} · {finding.confidence} %
        </span>
      </div>

      {finding.snippet && <p className="mt-2 text-sm text-slate-700">{finding.snippet}</p>}

      <div className="mt-2 flex flex-wrap gap-1.5 text-xs">
        {finding.matched.map((type) => (
          <span key={type} className="rounded bg-indigo-50 px-1.5 py-0.5 text-indigo-800">
            {DATA_TYPE_LABELS[type]}
          </span>
        ))}
        <span className="text-slate-500">
          · {FINDING_SOURCE_LABELS[finding.source]}
          {finding.organization ? ` · ${finding.organization.name}` : ' · organisme inconnu'}
          {finding.meta.verified === false && ' · page non relue (extrait du moteur seulement)'}
        </span>
      </div>

      <div className="mt-3 flex flex-wrap gap-2">
        {finding.erasure_request_id !== null ? (
          <Link to={`/requests/${finding.erasure_request_id}`} className="text-sm font-medium text-indigo-600 underline">
            Voir la demande créée
          </Link>
        ) : finding.status === 'new' ? (
          <>
            <Button onClick={() => onDecide('confirmed')} disabled={busy}>
              C'est moi
            </Button>
            <Button variant="secondary" onClick={() => onDecide('rejected')} disabled={busy}>
              Ce n'est pas moi
            </Button>
            <Button variant="ghost" onClick={() => onDecide('ignored')} disabled={busy}>
              Ignorer
            </Button>
          </>
        ) : (
          <Button variant="ghost" onClick={() => onDecide('new')} disabled={busy}>
            Annuler ma décision
          </Button>
        )}
        {finding.status === 'confirmed' && finding.erasure_request_id === null && !finding.organization && (
          <Link to={`/requests/new?url=${encodeURIComponent(finding.url)}`} className="self-center text-sm text-indigo-600 underline">
            Créer la demande à la main (organisme à ajouter)
          </Link>
        )}
      </div>
    </article>
  )
}
