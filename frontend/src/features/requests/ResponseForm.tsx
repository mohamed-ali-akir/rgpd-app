import { useState, type FormEvent } from 'react'
import { Alert } from '../../components/ui/Alert'
import { Button } from '../../components/ui/Button'
import { SelectField, TextareaField } from '../../components/ui/Field'
import { errorMessage } from '../../lib/errors'
import type { ErasureRequestDetail, ResponseOutcome } from '../../types/api'
import { useRecordResponse } from './useRequests'

const OUTCOME_LABELS: Record<ResponseOutcome, string> = {
  accepted: 'Mes données ont été supprimées (demande acceptée)',
  refused: 'L\'organisme refuse',
  extended: 'L\'organisme annonce une prolongation du délai (2 mois de plus)',
}

/**
 * Saisie de la réponse reçue. L'application ne lit pas la boîte Gmail (scope restreint) :
 * c'est l'utilisateur qui indique ce qu'il a reçu.
 */
export function ResponseForm({ request }: { request: ErasureRequestDetail }) {
  const record = useRecordResponse(request.id)
  // Seules les réponses autorisées dans le statut actuel sont proposées (calculées par le back).
  const [outcome, setOutcome] = useState<ResponseOutcome>(request.response_outcomes[0])
  const [note, setNote] = useState('')

  const submit = (event: FormEvent) => {
    event.preventDefault()
    record.mutate({ outcome, note }, { onSuccess: () => setNote('') })
  }

  return (
    <form onSubmit={submit} className="space-y-3">
      <SelectField
        label="Réponse reçue"
        value={outcome}
        onChange={(e) => setOutcome(e.target.value as ResponseOutcome)}
        options={request.response_outcomes.map((value) => ({ value, label: OUTCOME_LABELS[value] }))}
      />
      <TextareaField
        label="Note (facultatif)"
        rows={3}
        hint="Résumez la réponse ou collez-en un extrait : elle figurera dans l'historique et le dossier CNIL."
        value={note}
        onChange={(e) => setNote(e.target.value)}
      />
      {record.isError && <Alert tone="error">{errorMessage(record.error)}</Alert>}
      <Button type="submit" loading={record.isPending}>
        Enregistrer la réponse
      </Button>
    </form>
  )
}
