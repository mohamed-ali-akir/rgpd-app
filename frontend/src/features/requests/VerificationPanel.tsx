import { Alert } from '../../components/ui/Alert'
import { Button } from '../../components/ui/Button'
import { Card } from '../../components/ui/Card'
import { formatDate, formatDateTime } from '../../lib/dates'
import { errorMessage } from '../../lib/errors'
import { CHECK_OUTCOME_LABELS, DATA_TYPE_LABELS, VERIFICATION_LABELS } from '../../lib/labels'
import type { ErasureRequestDetail, VerificationStatus } from '../../types/api'
import { useVerifyRequest } from './useRequests'

const TONES: Record<VerificationStatus, 'info' | 'success' | 'warning' | 'error'> = {
  pending: 'info',
  cleared: 'success',
  still_present: 'error',
  unverifiable: 'warning',
}

const EXPLANATIONS: Record<VerificationStatus, string> = {
  pending: 'L\'application relit la page régulièrement pour vérifier que vos données ont bien disparu.',
  cleared: 'La page a été supprimée ou ne contient plus vos données.',
  still_present: 'Malgré l\'acceptation, vos données sont toujours publiées : vous pouvez relancer l\'organisme ou saisir la CNIL.',
  unverifiable: 'Le site n\'autorise pas la lecture automatique de cette page (ou elle est restée injoignable) : vérifiez-la vous-même.',
}

interface VerificationPanelProps {
  request: ErasureRequestDetail
  /** Appelé quand une vérification est lancée : la page relit la demande jusqu'au résultat. */
  onStarted: (checksBefore: number) => void
  waiting: boolean
}

/** Vérification que l'effacement accepté a vraiment eu lieu (V2). */
export function VerificationPanel({ request, onStarted, waiting }: VerificationPanelProps) {
  const verify = useVerifyRequest(request.id)
  if (request.verification === null) return null

  const status = request.verification

  return (
    <Card title="Vérification de l'effacement">
      <div className="space-y-4">
        <Alert tone={TONES[status]} title={VERIFICATION_LABELS[status]}>
          {EXPLANATIONS[status]}
          {status === 'pending' && request.next_check_at && <> Prochaine vérification : {formatDate(request.next_check_at)}.</>}
        </Alert>

        {request.checks.length > 0 && (
          <ul className="divide-y divide-slate-100 text-sm">
            {request.checks.map((check) => (
              <li key={check.id} className="flex flex-wrap justify-between gap-2 py-2">
                <span>
                  {CHECK_OUTCOME_LABELS[check.outcome]}
                  {check.matched.length > 0 && <span className="text-slate-500"> — {check.matched.map((t) => DATA_TYPE_LABELS[t]).join(', ')}</span>}
                </span>
                <span className="text-xs text-slate-500">
                  {formatDateTime(check.checked_at)}
                  {check.manual && ' · manuelle'}
                </span>
              </li>
            ))}
          </ul>
        )}

        {verify.isError && <Alert tone="error">{errorMessage(verify.error)}</Alert>}
        {waiting && <p className="text-sm text-slate-600">Vérification en cours…</p>}
        {request.available_actions.includes('verify') && (
          <Button
            variant="secondary"
            loading={verify.isPending || waiting}
            onClick={() => verify.mutate(undefined, { onSuccess: () => onStarted(request.checks.length) })}
          >
            Vérifier maintenant
          </Button>
        )}
      </div>
    </Card>
  )
}
