import { useState } from 'react'
import { useNavigate } from 'react-router'
import { Card } from '../../../components/ui/Card'
import { Spinner } from '../../../components/ui/Spinner'
import type { ErasureRequestDetail, Organization, ResolveResult } from '../../../types/api'
import { DraftEditor } from '../DraftEditor'
import { countSendFailures, type SendNavigationState } from '../sending'
import { useRequest } from '../useRequests'
import { DataStep } from './DataStep'
import { OrganizationStep } from './OrganizationStep'
import { UrlStep } from './UrlStep'

const STEPS = ['Adresse de la page', 'Organisme', 'Données concernées', 'Lettre et envoi'] as const

/**
 * Assistant en 4 étapes. Chaque étape est un composant indépendant ; l'assistant
 * garde seulement ce qui a été choisi (état "remonté" dans le parent) et l'étape courante.
 */
export function NewRequestWizard() {
  const navigate = useNavigate()
  const [step, setStep] = useState(0)
  const [url, setUrl] = useState('')
  const [resolved, setResolved] = useState<ResolveResult | null>(null)
  const [organization, setOrganization] = useState<Organization | null>(null)
  const [draftId, setDraftId] = useState<number | null>(null)

  return (
    <div className="space-y-6">
      <ol className="grid grid-cols-2 gap-2 sm:grid-cols-4" aria-label="Étapes">
        {STEPS.map((label, index) => (
          <li
            key={label}
            aria-current={index === step ? 'step' : undefined}
            className={`rounded-md border-t-4 px-2 pt-2 text-xs font-medium ${index <= step ? 'border-indigo-600 text-indigo-700' : 'border-slate-200 text-slate-500'}`}
          >
            Étape {index + 1}
            <span className="block text-sm text-slate-800">{label}</span>
          </li>
        ))}
      </ol>

      {step === 0 && (
        <Card>
          <UrlStep
            defaultUrl={url}
            onNext={(newUrl, result) => {
              setUrl(newUrl)
              setResolved(result)
              setStep(1)
            }}
          />
        </Card>
      )}

      {step === 1 && resolved && (
        <Card>
          <OrganizationStep
            resolved={resolved}
            onBack={() => setStep(0)}
            onSelect={(selected) => {
              setOrganization(selected)
              setStep(2)
            }}
          />
        </Card>
      )}

      {step === 2 && organization && (
        <Card>
          <DataStep
            url={url}
            organization={organization}
            onBack={() => setStep(1)}
            onCreated={(request) => {
              setDraftId(request.id)
              setStep(3)
            }}
          />
        </Card>
      )}

      {step === 3 && draftId !== null && (
        <WizardDraft
          id={draftId}
          // Après l'envoi, on passe à la page de détail (frise du suivi). "state" lui indique
          // qu'un envoi vient d'être lancé, pour qu'elle surveille le résultat du worker.
          onSent={(request) => {
            const state: SendNavigationState = { failuresAtSend: countSendFailures(request) }
            navigate(`/requests/${request.id}`, { state })
          }}
        />
      )}
    </div>
  )
}

/**
 * Le brouillon est lu depuis le cache TanStack Query (rempli à la création) plutôt que gardé
 * dans un useState : quand on régénère ou modifie la lettre, la mutation met le cache à jour
 * et l'écran suit automatiquement.
 */
function WizardDraft({ id, onSent }: { id: number; onSent: (request: ErasureRequestDetail) => void }) {
  const { data: request } = useRequest(id)
  if (!request) return <Spinner />

  return (
    <>
      <p className="text-sm text-slate-600">Votre brouillon est enregistré : vous pouvez le retrouver plus tard depuis le tableau de bord.</p>
      <DraftEditor request={request} onSent={onSent} />
    </>
  )
}
