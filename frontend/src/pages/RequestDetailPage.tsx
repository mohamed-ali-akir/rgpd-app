import { useState } from 'react'
import { Link, useLocation, useParams } from 'react-router'
import { Alert } from '../components/ui/Alert'
import { Card } from '../components/ui/Card'
import { PageHeader } from '../components/ui/PageHeader'
import { Spinner } from '../components/ui/Spinner'
import { StatusBadge } from '../components/ui/StatusBadge'
import { DraftEditor } from '../features/requests/DraftEditor'
import { RequestActions } from '../features/requests/RequestActions'
import { Timeline } from '../features/requests/Timeline'
import { VerificationPanel } from '../features/requests/VerificationPanel'
import { useRequest } from '../features/requests/useRequests'
import { deadlineText, formatDate } from '../lib/dates'
import { errorMessage } from '../lib/errors'
import { DATA_TYPE_LABELS, KIND_LABELS, LEGAL_GROUND_LABELS } from '../lib/labels'
import { countSendFailures, type SendNavigationState } from '../features/requests/sending'

export function RequestDetailPage() {
  // useParams lit les paramètres de l'URL : /requests/:id → { id: "12" }
  const id = Number(useParams().id)
  const location = useLocation()

  /*
   * Envoi Gmail en cours : le worker envoie en arrière-plan. On mémorise le nombre d'échecs
   * présents au moment de l'envoi (null = pas d'envoi en cours). Tant que la demande est en
   * brouillon et qu'aucun nouvel échec n'apparaît, on relit la demande toutes les 2 secondes.
   * Valeur initiale : transmise par l'assistant via l'état de navigation.
   */
  const [failuresAtSend, setFailuresAtSend] = useState<number | null>(
    (location.state as SendNavigationState | null)?.failuresAtSend ?? null,
  )
  /*
   * Vérification d'effacement lancée à la main : même principe, on attend qu'une nouvelle vérification
   * apparaisse. Limite de 2 minutes : si le worker est arrêté, la page n'interroge pas le serveur sans fin.
   */
  const [verifyStarted, setVerifyStarted] = useState<{ checksBefore: number; at: number } | null>(null)

  const query = useRequest(id, (r) => {
    const sending = failuresAtSend !== null && r.status === 'draft' && countSendFailures(r) <= failuresAtSend
    const verifying = verifyStarted !== null && r.checks.length <= verifyStarted.checksBefore && Date.now() - verifyStarted.at < 120_000
    return sending || verifying
  })
  const request = query.data

  if (query.isPending) return <Spinner />
  if (query.isError || !request) return <Alert tone="error">{errorMessage(query.error)}</Alert>

  const sendFailed = failuresAtSend !== null && countSendFailures(request) > failuresAtSend
  const sending = failuresAtSend !== null && request.status === 'draft' && !sendFailed

  const waiting = request.status === 'sent' || request.status === 'extended'
  const verifying = verifyStarted !== null && request.checks.length <= verifyStarted.checksBefore

  return (
    <>
      <PageHeader
        title={request.organization.name}
        description={
          <span className="break-all">
            <a href={request.url} target="_blank" rel="noreferrer" className="underline">
              {request.url}
            </a>
          </span>
        }
        actions={
          <div className="flex items-center gap-2">
            <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">{KIND_LABELS[request.kind]}</span>
            <StatusBadge status={request.status} />
          </div>
        }
      />

      <div className="space-y-6">
        {sending && <Alert tone="info">Envoi en cours depuis Gmail…</Alert>}
        {sendFailed && request.status === 'draft' && (
          <Alert tone="error" title="L'envoi a échoué">
            Consultez l'historique ci-dessous. Si Gmail a été déconnecté, reconnectez-le dans les{' '}
            <Link to="/settings" className="underline">paramètres</Link>, puis réessayez.
          </Alert>
        )}

        <Card>
          <dl className="grid gap-3 text-sm sm:grid-cols-3">
            <div>
              <dt className="text-slate-500">Envoyée le</dt>
              <dd className="font-medium">{formatDate(request.sent_at)}</dd>
            </div>
            <div>
              <dt className="text-slate-500">Échéance légale</dt>
              <dd className="font-medium">
                {formatDate(request.due_at)}
                {waiting && request.due_at && <span className="block text-xs font-normal text-slate-500">{deadlineText(request.due_at)}</span>}
              </dd>
            </div>
            <div>
              <dt className="text-slate-500">Canal</dt>
              <dd className="font-medium">{request.channel === 'gmail' ? 'Gmail (par l\'application)' : 'Envoi par vos soins'}</dd>
            </div>
            <div className="sm:col-span-3">
              <dt className="text-slate-500">Données concernées</dt>
              <dd>{request.data_types.length > 0 ? request.data_types.map((t) => DATA_TYPE_LABELS[t]).join(', ') : 'Toutes les données me concernant'}</dd>
            </div>
            {request.legal_grounds.length > 0 && (
              <div className="sm:col-span-3">
                <dt className="text-slate-500">Fondements</dt>
                <dd>{request.legal_grounds.map((g) => LEGAL_GROUND_LABELS[g]).join(' · ')}</dd>
              </div>
            )}
            {request.details && (
              <div className="sm:col-span-3">
                <dt className="text-slate-500">{request.kind === 'rectification' ? 'Correction demandée' : 'Motif'}</dt>
                <dd className="whitespace-pre-line">{request.details}</dd>
              </div>
            )}
          </dl>
        </Card>

        {request.status === 'draft' ? (
          <DraftEditor request={request} onSent={(sent) => setFailuresAtSend(countSendFailures(sent))} />
        ) : (
          <>
            <VerificationPanel
              request={request}
              waiting={verifying}
              onStarted={(checksBefore) => setVerifyStarted({ checksBefore, at: Date.now() })}
            />
            <RequestActions request={request} />
            <details className="rounded-lg bg-white p-5 ring-1 ring-slate-200">
              <summary className="cursor-pointer text-sm font-semibold text-slate-900">Lettre envoyée</summary>
              <p className="mt-3 text-sm font-medium">{request.subject}</p>
              <pre className="mt-2 font-sans text-sm whitespace-pre-wrap text-slate-700">{request.body}</pre>
            </details>
          </>
        )}

        <Card title="Historique">
          <Timeline events={request.events} />
        </Card>
      </div>
    </>
  )
}
