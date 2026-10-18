import { Link } from 'react-router'
import { Alert } from '../components/ui/Alert'
import { Button } from '../components/ui/Button'
import { Card } from '../components/ui/Card'
import { PageHeader } from '../components/ui/PageHeader'
import { Spinner } from '../components/ui/Spinner'
import { FindingCard } from '../features/scanner/FindingCard'
import { GuidedChecks } from '../features/scanner/GuidedChecks'
import { ScanPanel } from '../features/scanner/ScanPanel'
import { useCreateRequestsFromFindings, useDecideFinding, useFindings } from '../features/scanner/useScanner'
import { errorMessage } from '../lib/errors'
import { SKIP_REASON_LABELS } from '../lib/labels'
import type { Finding } from '../types/api'

/** Résultats regroupés : à examiner, confirmés, écartés. */
function group(findings: Finding[]) {
  return {
    toReview: findings.filter((f) => f.status === 'new'),
    confirmed: findings.filter((f) => f.status === 'confirmed'),
    dismissed: findings.filter((f) => f.status === 'rejected' || f.status === 'ignored'),
  }
}

export function ScannerPage() {
  const findings = useFindings()
  const decide = useDecideFinding()
  const createRequests = useCreateRequestsFromFindings()

  const groups = group(findings.data ?? [])
  const readyForRequest = groups.confirmed.filter((f) => f.erasure_request_id === null)

  const renderList = (list: Finding[]) => (
    <div className="space-y-3">
      {list.map((finding) => (
        <FindingCard
          key={finding.id}
          finding={finding}
          busy={decide.isPending && decide.variables?.id === finding.id}
          onDecide={(status) => decide.mutate({ id: finding.id, status })}
        />
      ))}
    </div>
  )

  return (
    <>
      <PageHeader title="Mes données en ligne" description="Trouvez les pages qui publient vos données personnelles, puis demandez leur effacement." />

      <div className="space-y-6">
        <ScanPanel />

        {findings.isPending && <Spinner />}
        {findings.isError && <Alert tone="error">{errorMessage(findings.error)}</Alert>}
        {decide.isError && <Alert tone="error">{errorMessage(decide.error)}</Alert>}

        {groups.toReview.length > 0 && (
          <Card title={`À examiner (${groups.toReview.length})`}>
            <p className="mb-3 text-sm text-slate-600">
              Une même personne peut porter votre nom : confirmez seulement les pages qui parlent bien de vous.
            </p>
            {renderList(groups.toReview)}
          </Card>
        )}

        {groups.confirmed.length > 0 && (
          <Card
            title={`Confirmés (${groups.confirmed.length})`}
            actions={
              readyForRequest.length > 0 && (
                <Button loading={createRequests.isPending} onClick={() => createRequests.mutate(readyForRequest.map((f) => f.id))}>
                  Préparer {readyForRequest.length > 1 ? `les ${readyForRequest.length} demandes` : 'la demande'} d'effacement
                </Button>
              )
            }
          >
            {createRequests.isError && <Alert tone="error">{errorMessage(createRequests.error)}</Alert>}
            {createRequests.data && (
              <div className="mb-3">
                <Alert tone={createRequests.data.created.length > 0 ? 'success' : 'info'}>
                  {createRequests.data.created.length} brouillon(s) créé(s) : relisez-les puis envoyez-les depuis le{' '}
                  <Link to="/" className="underline">tableau de bord</Link>.
                  {createRequests.data.skipped.length > 0 && (
                    <ul className="mt-1 list-disc pl-5">
                      {createRequests.data.skipped.map((skip) => (
                        <li key={skip.finding_id}>
                          {groups.confirmed.find((f) => f.id === skip.finding_id)?.domain ?? `Résultat ${skip.finding_id}`} :{' '}
                          {SKIP_REASON_LABELS[skip.reason]}
                        </li>
                      ))}
                    </ul>
                  )}
                </Alert>
              </div>
            )}
            {renderList(groups.confirmed)}
          </Card>
        )}

        {findings.data && findings.data.length === 0 && (
          <p className="text-center text-sm text-slate-500">Aucun résultat pour l'instant. Lancez une recherche ci-dessus.</p>
        )}

        <GuidedChecks />

        {groups.dismissed.length > 0 && (
          <details className="rounded-lg bg-white p-5 ring-1 ring-slate-200">
            <summary className="cursor-pointer text-sm font-semibold text-slate-900">Écartés ({groups.dismissed.length})</summary>
            <p className="mt-2 mb-3 text-xs text-slate-500">Supprimés automatiquement 6 mois après votre décision.</p>
            {renderList(groups.dismissed)}
          </details>
        )}
      </div>
    </>
  )
}
