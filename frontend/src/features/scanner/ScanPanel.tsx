import { Alert } from '../../components/ui/Alert'
import { Button } from '../../components/ui/Button'
import { Card } from '../../components/ui/Card'
import { Spinner } from '../../components/ui/Spinner'
import { formatDateTime } from '../../lib/dates'
import { errorMessage } from '../../lib/errors'
import { FINDING_SOURCE_LABELS } from '../../lib/labels'
import type { FindingSource, Scan, SourceReport } from '../../types/api'
import { useUser } from '../auth/useAuth'
import { useScans, useStartScan } from './useScanner'

const REPORT_TEXT: Record<SourceReport['status'], (report: SourceReport) => string> = {
  ok: (report) => `${report.found ?? 0} résultat(s)`,
  failed: () => 'indisponible pour le moment',
  skipped: () => 'non configurée',
}

const SOURCES = Object.keys(FINDING_SOURCE_LABELS) as FindingSource[]

/** Lancement d'une recherche et suivi de la plus récente. */
export function ScanPanel() {
  const { data: user } = useUser()
  const scans = useScans()
  const start = useStartScan()
  const latest: Scan | undefined = scans.data?.[0]
  const active = latest?.status === 'queued' || latest?.status === 'running'

  return (
    <Card title="Rechercher mes données en ligne">
      <div className="space-y-4 text-sm">
        <p className="text-slate-600">
          L'application cherche <strong>votre</strong> nom, votre e-mail et votre téléphone dans les registres d'entreprises et sur
          le web, puis relit les pages trouvées pour confirmer. Vous décidez ensuite, résultat par résultat : rien n'est envoyé sans
          vous.
        </p>

        {!user?.email_verified && <Alert tone="warning">Vérifiez votre adresse e-mail pour pouvoir lancer une recherche.</Alert>}
        {start.isError && <Alert tone="error">{errorMessage(start.error)}</Alert>}

        {active ? (
          <div className="flex items-center gap-2 text-indigo-700" role="status">
            <span className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden />
            Recherche en cours… cela peut prendre quelques minutes. Vous pouvez quitter la page.
          </div>
        ) : (
          <Button onClick={() => start.mutate()} loading={start.isPending} disabled={!user?.email_verified}>
            {latest ? 'Relancer une recherche' : 'Lancer ma première recherche'}
          </Button>
        )}

        {scans.isPending && <Spinner />}
        {latest && !active && (
          <div className="rounded-md bg-slate-50 p-3">
            <p className="font-medium text-slate-800">
              Dernière recherche : {formatDateTime(latest.finished_at ?? latest.created_at)}
              {latest.status === 'failed' && <span className="text-red-700"> — échouée</span>}
              {latest.status === 'completed' && ` — ${latest.new_findings_count} nouveau(x) résultat(s)`}
            </p>
            <ul className="mt-1 space-y-0.5 text-xs text-slate-600">
              {/* Ordre fixe des sources : MySQL ne conserve pas l'ordre des clés du rapport (colonne JSON). */}
              {SOURCES.map((source) => {
                const report = latest.report[source]
                return (
                  report && (
                    <li key={source}>
                      {FINDING_SOURCE_LABELS[source]} : {REPORT_TEXT[report.status](report)}
                    </li>
                  )
                )
              })}
            </ul>
          </div>
        )}
      </div>
    </Card>
  )
}
