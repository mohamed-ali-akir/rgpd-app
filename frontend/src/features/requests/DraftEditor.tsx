import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router'
import { LegalDisclaimer } from '../../components/LegalDisclaimer'
import { Alert } from '../../components/ui/Alert'
import { Button } from '../../components/ui/Button'
import { Card } from '../../components/ui/Card'
import { Checkbox, InputField, TextareaField } from '../../components/ui/Field'
import { todayIsoDate } from '../../lib/dates'
import { errorMessage } from '../../lib/errors'
import { LEGAL_GROUNDS, LEGAL_GROUND_LABELS } from '../../lib/labels'
import type { ErasureRequestDetail, LegalGround } from '../../types/api'
import { useUser } from '../auth/useAuth'
import { useMarkSent, useSendRequest, useUpdateRequest } from './useRequests'

interface DraftEditorProps {
  request: ErasureRequestDetail
  /** Appelé après l'envoi Gmail (mis en file) ou la confirmation d'un envoi manuel. */
  onSent: (request: ErasureRequestDetail) => void
}

interface LetterValues {
  subject: string
  body: string
}

/** Aperçu modifiable de la lettre, avertissements juridiques et envoi d'un brouillon. */
export function DraftEditor({ request, onSent }: DraftEditorProps) {
  const { data: user } = useUser()
  const update = useUpdateRequest(request.id)
  const send = useSendRequest(request.id)
  const markSent = useMarkSent(request.id)

  const [grounds, setGrounds] = useState<LegalGround[]>(request.legal_grounds)
  const [details, setDetails] = useState(request.details ?? '')
  const isErasure = request.kind === 'erasure'
  const hasDetails = request.kind === 'rectification' || request.kind === 'restriction'
  const [sentAt, setSentAt] = useState(todayIsoDate())
  const [copied, setCopied] = useState(false)

  const {
    register,
    handleSubmit,
    reset,
    getValues,
    formState: { isDirty },
  } = useForm<LetterValues>({ defaultValues: { subject: request.subject, body: request.body } })

  /*
   * useEffect : code à exécuter APRÈS l'affichage, quand une dépendance change.
   * Ici : si la lettre a été régénérée côté serveur, on recharge le formulaire avec le nouveau texte.
   * Le tableau [request.subject, request.body, reset] liste ces dépendances.
   */
  useEffect(() => {
    reset({ subject: request.subject, body: request.body })
  }, [request.subject, request.body, reset])

  const toggleGround = (ground: LegalGround) =>
    setGrounds((current) => (current.includes(ground) ? current.filter((g) => g !== ground) : [...current, ground]))

  const regenerate = () => {
    if (isDirty && !window.confirm('Régénérer la lettre remplacera vos modifications. Continuer ?')) return
    // Seuls les champs propres au droit exercé sont envoyés (le serveur refuse les autres).
    update.mutate(isErasure ? { legal_grounds: grounds, regenerate_letter: true } : { details, regenerate_letter: true })
  }

  const copyLetter = async () => {
    const { subject, body } = getValues()
    await navigator.clipboard.writeText(`${subject}\n\n${body}`)
    setCopied(true)
  }

  const organization = request.organization
  const gmailReady = user?.email_verified && user.google_email
  const actionError = update.error ?? send.error ?? markSent.error

  return (
    <div className="space-y-6">
      {request.warnings.length > 0 && (
        <div className="space-y-2">
          {request.warnings.map((warning) => (
            <Alert key={warning.code} tone="warning">
              {warning.message}
            </Alert>
          ))}
          {organization.privacy_form_url && organization.category === 'search_engine' && (
            <p className="text-sm">
              Formulaire de déréférencement :{' '}
              <a href={organization.privacy_form_url} target="_blank" rel="noreferrer" className="text-indigo-600 underline">
                {organization.privacy_form_url}
              </a>
            </p>
          )}
        </div>
      )}
      <LegalDisclaimer />

      {isErasure && (
        <Card title="Fondements juridiques">
          <div className="grid gap-2 sm:grid-cols-2">
            {LEGAL_GROUNDS.map((ground) => (
              <Checkbox key={ground} label={LEGAL_GROUND_LABELS[ground]} checked={grounds.includes(ground)} onChange={() => toggleGround(ground)} />
            ))}
          </div>
          <div className="mt-3">
            <Button variant="secondary" onClick={regenerate} loading={update.isPending} disabled={grounds.length === 0}>
              Régénérer la lettre avec ces fondements
            </Button>
          </div>
        </Card>
      )}

      {hasDetails && (
        <Card title={request.kind === 'rectification' ? 'Correction demandée' : 'Motif de la limitation'}>
          <TextareaField label="Texte repris dans la lettre" rows={3} value={details} onChange={(e) => setDetails(e.target.value)} />
          <div className="mt-3">
            <Button variant="secondary" onClick={regenerate} loading={update.isPending} disabled={details.trim() === ''}>
              Régénérer la lettre
            </Button>
          </div>
        </Card>
      )}

      <Card title="Votre lettre">
        <form onSubmit={handleSubmit((values) => update.mutate(values))} className="space-y-4">
          <InputField label="Objet" {...register('subject', { required: true })} />
          <TextareaField label="Texte" rows={22} {...register('body', { required: true })} />
          <div className="flex flex-wrap gap-2">
            <Button type="submit" variant="secondary" disabled={!isDirty} loading={update.isPending}>
              Enregistrer les modifications
            </Button>
            <Button variant="ghost" onClick={copyLetter}>
              {copied ? 'Lettre copiée' : 'Copier la lettre'}
            </Button>
          </div>
        </form>
      </Card>

      <Card title="Envoi">
        {actionError && (
          <div className="mb-4">
            <Alert tone="error">{errorMessage(actionError)}</Alert>
          </div>
        )}
        {isDirty && <p className="mb-3 text-sm text-amber-800">Enregistrez vos modifications avant d'envoyer.</p>}

        {request.channel === 'gmail' ? (
          <div className="space-y-3 text-sm">
            <p>
              La lettre sera envoyée depuis <strong>{user?.google_email ?? 'votre adresse Gmail'}</strong> à{' '}
              <strong>{organization.privacy_email}</strong>. Le délai légal d'un mois commencera à courir.
            </p>
            {!user?.email_verified && <Alert tone="warning">Vérifiez d'abord votre adresse e-mail (lien reçu à l'inscription).</Alert>}
            {user?.email_verified && !user.google_email && (
              <Alert tone="warning">
                Connectez votre compte Gmail dans les <Link to="/settings" className="font-semibold underline">paramètres</Link>.
              </Alert>
            )}
            <div className="flex flex-wrap gap-2">
              <Button disabled={!gmailReady || isDirty} loading={send.isPending} onClick={() => send.mutate(undefined, { onSuccess: onSent })}>
                Envoyer depuis Gmail
              </Button>
              <Button variant="ghost" loading={update.isPending} onClick={() => update.mutate({ channel: 'manual' })}>
                Je préfère l'envoyer moi-même
              </Button>
            </div>
          </div>
        ) : (
          <div className="space-y-3 text-sm">
            <p>Envoyez la lettre vous-même, puis confirmez l'envoi ici pour que l'application suive le délai légal.</p>
            <ul className="list-disc space-y-1 pl-5 text-slate-700">
              {organization.privacy_form_url && (
                <li>
                  Formulaire :{' '}
                  <a href={organization.privacy_form_url} target="_blank" rel="noreferrer" className="break-all text-indigo-600 underline">
                    {organization.privacy_form_url}
                  </a>
                </li>
              )}
              {organization.privacy_email && <li>E-mail : {organization.privacy_email}</li>}
              {!organization.privacy_form_url && !organization.privacy_email && (
                <li>Cherchez le contact « données personnelles » dans la politique de confidentialité ou les mentions légales du site.</li>
              )}
              <li>Par courrier : de préférence en recommandé avec accusé de réception, pour garder une preuve.</li>
            </ul>
            <div className="flex flex-wrap items-end gap-3">
              <div className="w-48">
                <InputField label="Date d'envoi" type="date" max={todayIsoDate()} value={sentAt} onChange={(e) => setSentAt(e.target.value)} />
              </div>
              <Button disabled={isDirty || !sentAt} loading={markSent.isPending} onClick={() => markSent.mutate(sentAt, { onSuccess: onSent })}>
                J'ai envoyé ma demande
              </Button>
              {gmailReady && organization.privacy_email && organization.category !== 'search_engine' && (
                <Button variant="ghost" loading={update.isPending} onClick={() => update.mutate({ channel: 'gmail' })}>
                  Envoyer plutôt depuis Gmail
                </Button>
              )}
            </div>
          </div>
        )}
      </Card>
    </div>
  )
}
