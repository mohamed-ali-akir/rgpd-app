import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { Alert } from '../components/ui/Alert'
import { buttonClasses } from '../components/ui/buttonClasses'
import { Card } from '../components/ui/Card'
import { SelectField } from '../components/ui/Field'
import { PageHeader } from '../components/ui/PageHeader'
import { Spinner } from '../components/ui/Spinner'
import { RequestList } from '../features/requests/RequestList'
import { useDashboard, useRequests } from '../features/requests/useRequests'
import { errorMessage } from '../lib/errors'
import { STATUSES, STATUS_LABELS } from '../lib/labels'
import type { RequestStatus } from '../types/api'

export function DashboardPage() {
  const [searchParams] = useSearchParams()

  /*
   * useState : une valeur "d'état" propre au composant. Quand on appelle setStatus(...),
   * React ré-exécute la fonction DashboardPage avec la nouvelle valeur et met l'écran à jour.
   */
  const [status, setStatus] = useState<RequestStatus | ''>('')

  const dashboard = useDashboard()
  const requests = useRequests(status || undefined)

  return (
    <>
      <PageHeader
        title="Tableau de bord"
        description="Suivez vos demandes d'effacement et leurs délais légaux."
        actions={
          <Link to="/requests/new" className={buttonClasses('primary')}>
            Nouvelle demande
          </Link>
        }
      />

      {searchParams.get('verified') === '1' && (
        <div className="mb-6">
          <Alert tone="success">Adresse e-mail vérifiée. Vous pouvez maintenant envoyer vos demandes depuis Gmail.</Alert>
        </div>
      )}

      {dashboard.isPending && <Spinner />}
      {dashboard.isError && <Alert tone="error">{errorMessage(dashboard.error)}</Alert>}

      {dashboard.data && (
        <div className="space-y-6">
          {/* Les demandes en retard en premier : ce sont celles qui demandent une action. */}
          {dashboard.data.overdue.length > 0 && (
            <Card title="Délai dépassé : à relancer ou à signaler à la CNIL" className="ring-amber-300">
              <RequestList requests={dashboard.data.overdue} emptyText="" />
            </Card>
          )}

          <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {STATUSES.map((s) => (
              <button
                key={s}
                type="button"
                onClick={() => setStatus(status === s ? '' : s)}
                aria-pressed={status === s}
                className={`rounded-lg bg-white p-3 text-left ring-1 hover:ring-indigo-300 ${status === s ? 'ring-2 ring-indigo-600' : 'ring-slate-200'}`}
              >
                <dt className="text-xs text-slate-500">{STATUS_LABELS[s]}</dt>
                <dd className="text-2xl font-semibold text-slate-900">{dashboard.data.counts[s]}</dd>
              </button>
            ))}
          </dl>

          <Card
            title="Mes demandes"
            actions={
              <div className="w-48">
                <SelectField
                  label="Filtrer par statut"
                  value={status}
                  onChange={(event) => setStatus(event.target.value as RequestStatus | '')}
                  options={[{ value: '', label: 'Tous les statuts' }, ...STATUSES.map((s) => ({ value: s, label: STATUS_LABELS[s] }))]}
                />
              </div>
            }
          >
            {requests.isPending ? (
              <Spinner />
            ) : requests.isError ? (
              <Alert tone="error">{errorMessage(requests.error)}</Alert>
            ) : (
              <RequestList
                requests={requests.data}
                emptyText={status ? 'Aucune demande avec ce statut.' : 'Aucune demande pour le moment. Commencez par coller l\'adresse d\'une page qui vous concerne.'}
              />
            )}
          </Card>
        </div>
      )}
    </>
  )
}
