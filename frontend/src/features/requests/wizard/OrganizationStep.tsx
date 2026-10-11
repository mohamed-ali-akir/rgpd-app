import { useState } from 'react'
import { Button } from '../../../components/ui/Button'
import type { Organization, ResolveResult } from '../../../types/api'
import { OrganizationCard } from '../../organizations/OrganizationCard'
import { OrganizationForm } from '../../organizations/OrganizationForm'
import { OrganizationSearch } from '../../organizations/OrganizationSearch'

interface OrganizationStepProps {
  resolved: ResolveResult
  onBack: () => void
  onSelect: (organization: Organization) => void
}

type Mode = 'detected' | 'search' | 'create'

/** Étape 2 : confirmer l'organisme détecté, en chercher un autre, ou le créer. */
export function OrganizationStep({ resolved, onBack, onSelect }: OrganizationStepProps) {
  const [mode, setMode] = useState<Mode>(resolved.organization ? 'detected' : 'search')

  return (
    <div className="space-y-4">
      {mode === 'detected' && resolved.organization && (
        <>
          <p className="text-sm text-slate-700">Organisme détecté pour {resolved.domain} :</p>
          <OrganizationCard
            organization={resolved.organization}
            actions={<Button onClick={() => onSelect(resolved.organization!)}>Choisir cet organisme</Button>}
          />
          <button type="button" className="text-sm text-indigo-600 underline" onClick={() => setMode('search')}>
            Ce n'est pas le bon organisme ?
          </button>
        </>
      )}

      {mode === 'search' && (
        <>
          {!resolved.organization && (
            <p className="text-sm text-slate-700">
              Aucun organisme connu pour <strong>{resolved.domain}</strong>. Cherchez-le sous un autre nom, ou ajoutez-le.
            </p>
          )}
          <OrganizationSearch renderActions={(organization) => <Button variant="secondary" onClick={() => onSelect(organization)}>Choisir</Button>} />
          <Button variant="secondary" onClick={() => setMode('create')}>
            Ajouter un nouvel organisme
          </Button>
        </>
      )}

      {mode === 'create' && <OrganizationForm defaultDomain={resolved.domain} onCreated={onSelect} />}

      <div className="flex justify-start">
        <Button variant="ghost" onClick={onBack}>
          ← Retour
        </Button>
      </div>
    </div>
  )
}
