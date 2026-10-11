import { useState } from 'react'
import { useNavigate } from 'react-router'
import { complaintPdfUrl } from '../../api/requests'
import { Alert } from '../../components/ui/Alert'
import { Button } from '../../components/ui/Button'
import { buttonClasses } from '../../components/ui/buttonClasses'
import { Card } from '../../components/ui/Card'
import { TextareaField } from '../../components/ui/Field'
import { errorMessage } from '../../lib/errors'
import type { ErasureRequestDetail } from '../../types/api'
import { ResponseForm } from './ResponseForm'
import { useCloseRequest, useDeleteRequest, usePrepareComplaint, useSendReminder } from './useRequests'

/**
 * Actions disponibles selon le statut. La liste vient du back (available_actions) :
 * le front n'a pas à connaître la machine à états, il affiche seulement les bons boutons.
 */
export function RequestActions({ request }: { request: ErasureRequestDetail }) {
  const navigate = useNavigate()
  const reminder = useSendReminder(request.id)
  const complaint = usePrepareComplaint(request.id)
  const close = useCloseRequest(request.id)
  const remove = useDeleteRequest(request.id)
  const [reminderCopied, setReminderCopied] = useState(false)

  const can = (action: ErasureRequestDetail['available_actions'][number]) => request.available_actions.includes(action)
  const error = reminder.error ?? complaint.error ?? close.error ?? remove.error

  const confirmDelete = () => {
    if (window.confirm('Supprimer définitivement cette demande et son historique ?')) {
      remove.mutate(undefined, { onSuccess: () => navigate('/', { replace: true }) })
    }
  }

  return (
    <div className="space-y-6">
      {error && <Alert tone="error">{errorMessage(error)}</Alert>}

      {can('reminder') && (
        <Card title="Relancer l'organisme">
          <p className="mb-3 text-sm text-slate-700">
            Le délai légal est dépassé. Une relance rappelle ses obligations à l'organisme avant de saisir la CNIL.
          </p>
          {request.channel === 'gmail' ? (
            <Button loading={reminder.isPending} onClick={() => reminder.mutate()}>
              Envoyer la relance depuis Gmail
            </Button>
          ) : (
            request.reminder && (
              <div className="space-y-3">
                <TextareaField label={`Texte de la relance — objet : ${request.reminder.subject}`} rows={12} readOnly value={request.reminder.body} />
                <div className="flex flex-wrap gap-2">
                  <Button
                    variant="secondary"
                    onClick={async () => {
                      await navigator.clipboard.writeText(`${request.reminder!.subject}\n\n${request.reminder!.body}`)
                      setReminderCopied(true)
                    }}
                  >
                    {reminderCopied ? 'Relance copiée' : 'Copier la relance'}
                  </Button>
                  <Button loading={reminder.isPending} onClick={() => reminder.mutate()}>
                    J'ai envoyé la relance
                  </Button>
                </div>
              </div>
            )
          )}
        </Card>
      )}

      {can('respond') && (
        <Card title="J'ai reçu une réponse">
          <ResponseForm request={request} />
        </Card>
      )}

      {can('complaint') && (
        <Card title="Saisir la CNIL">
          <p className="mb-3 text-sm text-slate-700">
            En cas de refus ou d'absence de réponse, vous pouvez déposer une réclamation auprès de la CNIL (art. 77 du RGPD).
            L'application prépare un dossier PDF récapitulant votre demande et son historique.
          </p>
          <Button loading={complaint.isPending} onClick={() => complaint.mutate()}>
            Préparer la plainte CNIL
          </Button>
        </Card>
      )}

      {can('download_complaint') && (
        <Card title="Dossier de plainte">
          <p className="mb-3 text-sm text-slate-700">
            Téléchargez le dossier, puis déposez votre réclamation vous-même sur le site de la CNIL (www.cnil.fr) en le joignant.
          </p>
          {/* Lien classique : c'est le navigateur qui télécharge le fichier. */}
          <a href={complaintPdfUrl(request.id)} className={buttonClasses('primary')}>
            Télécharger le dossier PDF
          </a>
        </Card>
      )}

      <div className="flex flex-wrap gap-2">
        {can('close') && (
          <Button variant="secondary" loading={close.isPending} onClick={() => close.mutate()}>
            Clôturer la demande
          </Button>
        )}
        {can('delete') && (
          <Button variant="danger" loading={remove.isPending} onClick={confirmDelete}>
            Supprimer la demande
          </Button>
        )}
      </div>
    </div>
  )
}
