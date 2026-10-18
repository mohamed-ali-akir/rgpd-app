import { Alert } from '../../components/ui/Alert'
import { Card } from '../../components/ui/Card'
import { Spinner } from '../../components/ui/Spinner'
import { errorMessage } from '../../lib/errors'
import { useGuidedChecks } from './useScanner'

/**
 * Ce que l'automate ne peut pas lire (réseaux sociaux, annuaires fermés aux robots) :
 * l'utilisateur vérifie lui-même en un clic, la recherche s'ouvre dans un nouvel onglet.
 */
export function GuidedChecks() {
  const checks = useGuidedChecks()

  return (
    <Card title="Vérifications à faire vous-même">
      <p className="mb-3 text-sm text-slate-600">
        Certains sites interdisent la lecture automatique. Chaque lien ouvre une recherche de votre nom sur le site. Si vous
        trouvez une page, copiez son adresse et créez une demande.
      </p>
      {checks.isPending && <Spinner />}
      {checks.isError && <Alert tone="error">{errorMessage(checks.error)}</Alert>}
      <ul className="grid gap-2 sm:grid-cols-2">
        {checks.data?.map((check) => (
          <li key={check.organization.id}>
            <a
              href={check.search_url}
              target="_blank"
              rel="noreferrer"
              className="flex items-center justify-between rounded-md px-3 py-2 text-sm ring-1 ring-slate-200 hover:bg-slate-50"
            >
              <span className="font-medium text-slate-800">{check.organization.name}</span>
              <span className="text-xs text-slate-500">{check.organization.category_label} ↗</span>
            </a>
          </li>
        ))}
      </ul>
    </Card>
  )
}
