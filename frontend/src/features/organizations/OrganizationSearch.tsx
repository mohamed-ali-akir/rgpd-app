import { useDeferredValue, useState, type ReactNode } from 'react'
import { Alert } from '../../components/ui/Alert'
import { InputField } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { errorMessage } from '../../lib/errors'
import type { Organization } from '../../types/api'
import { OrganizationCard } from './OrganizationCard'
import { useOrganizations } from './useOrganizations'

interface OrganizationSearchProps {
  /** Boutons affichés sous chaque organisme (ex. « Choisir » dans l'assistant). */
  renderActions?: (organization: Organization) => ReactNode
}

export function OrganizationSearch({ renderActions }: OrganizationSearchProps) {
  const [search, setSearch] = useState('')
  /*
   * useDeferredValue : React met à jour le champ tout de suite, mais "search différé"
   * ne suit qu'avec un léger retard quand l'utilisateur tape vite. On évite ainsi
   * de lancer une requête HTTP à chaque lettre.
   */
  const deferredSearch = useDeferredValue(search)
  const organizations = useOrganizations(deferredSearch)

  return (
    <div className="space-y-4">
      <InputField label="Rechercher un organisme" type="search" placeholder="Nom ou domaine" value={search} onChange={(e) => setSearch(e.target.value)} />
      {organizations.isPending && <Spinner />}
      {organizations.isError && <Alert tone="error">{errorMessage(organizations.error)}</Alert>}
      {organizations.data && organizations.data.length === 0 && <p className="text-sm text-slate-500">Aucun organisme trouvé.</p>}
      <div className="grid gap-3 sm:grid-cols-2">
        {organizations.data?.map((organization) => (
          <OrganizationCard key={organization.id} organization={organization} actions={renderActions?.(organization)} />
        ))}
      </div>
    </div>
  )
}
