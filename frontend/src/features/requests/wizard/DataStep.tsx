import { zodResolver } from '@hookform/resolvers/zod'
import { useForm, useWatch } from 'react-hook-form'
import { Alert } from '../../../components/ui/Alert'
import { Button } from '../../../components/ui/Button'
import { Checkbox, FieldError, TextareaField } from '../../../components/ui/Field'
import { gmailPossible } from '../../../lib/channels'
import { applyServerErrors, errorMessage } from '../../../lib/errors'
import { DATA_TYPES, DATA_TYPE_LABELS, KINDS, KIND_DESCRIPTIONS, KIND_LABELS } from '../../../lib/labels'
import { dataStepSchema, type DataStepValues } from '../../../lib/schemas'
import type { ErasureRequestDetail, Organization } from '../../../types/api'
import { useCreateRequest } from '../useRequests'

interface DataStepProps {
  url: string
  organization: Organization
  onBack: () => void
  onCreated: (request: ErasureRequestDetail) => void
}

/** Étape 3 : données concernées, origine des données, canal d'envoi → création du brouillon. */
export function DataStep({ url, organization, onBack, onCreated }: DataStepProps) {
  const canUseGmail = gmailPossible(organization)
  const create = useCreateRequest()
  const {
    register,
    handleSubmit,
    setError,
    control,
    formState: { errors },
  } = useForm<DataStepValues>({
    resolver: zodResolver(dataStepSchema),
    defaultValues: {
      kind: 'erasure',
      data_types: [],
      details: '',
      self_published: false,
      has_account: false,
      channel: canUseGmail ? 'gmail' : 'manual',
      own_data_confirmed: false,
    },
  })

  // useWatch : abonne le composant à ce champ, pour adapter le formulaire au droit choisi.
  const kind = useWatch({ control, name: 'kind' })
  const needsDetails = kind === 'rectification' || kind === 'restriction'

  const onSubmit = (values: DataStepValues) =>
    create.mutate(
      {
        ...values,
        url,
        organization_id: organization.id,
        own_data_confirmed: values.own_data_confirmed === true,
        // Le serveur refuse "details" pour l'effacement et l'accès : on ne l'envoie que si nécessaire.
        details: needsDetails ? values.details : undefined,
      },
      {
        onSuccess: onCreated,
        // Erreurs de validation de l'API → affichées sous les champs concernés.
        onError: (error) => applyServerErrors(error, setError),
      },
    )

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-6">
      <fieldset className="space-y-2">
        <legend className="text-sm font-medium text-slate-800">Quel droit voulez-vous exercer ?</legend>
        <div className="grid gap-2 sm:grid-cols-2">
          {KINDS.map((value) => (
            <label key={value} className="flex items-start gap-2 rounded-md p-2 text-sm ring-1 ring-slate-200 has-checked:bg-indigo-50 has-checked:ring-indigo-300">
              <input type="radio" value={value} className="mt-0.5 accent-indigo-600" {...register('kind')} />
              <span>
                <span className="font-medium text-slate-900">{KIND_LABELS[value]}</span>
                <span className="block text-xs text-slate-600">{KIND_DESCRIPTIONS[value]}</span>
              </span>
            </label>
          ))}
        </div>
      </fieldset>

      {needsDetails && (
        <TextareaField
          label={kind === 'rectification' ? 'Correction demandée' : 'Motif de la limitation'}
          rows={3}
          hint={kind === 'rectification' ? 'Exemple : remplacer « 12 rue des Lilas » par « 14 rue des Lilas ».' : "Exemple : je conteste l'exactitude de ces données."}
          error={errors.details?.message}
          {...register('details')}
        />
      )}

      <fieldset className="space-y-2">
        <legend className="text-sm font-medium text-slate-800">
          Quelles données vous concernant apparaissent sur la page ?{kind === 'access' && ' (facultatif)'}
        </legend>
        <div className="grid gap-2 sm:grid-cols-2">
          {DATA_TYPES.map((type) => (
            // Plusieurs cases avec le même nom : react-hook-form renvoie le tableau des valeurs cochées.
            <Checkbox key={type} label={DATA_TYPE_LABELS[type]} value={type} {...register('data_types')} />
          ))}
        </div>
        <FieldError message={errors.data_types?.message} />
      </fieldset>

      {kind === 'erasure' && (
      <fieldset className="space-y-2">
        <legend className="text-sm font-medium text-slate-800">D'où viennent ces données ?</legend>
        <Checkbox label="J'ai publié ces informations moi-même" {...register('self_published')} />
        <Checkbox label="J'ai (ou j'ai eu) un compte sur ce site" {...register('has_account')} />
        <p className="text-xs text-slate-500">Si vous cochez l'une de ces cases, la lettre ajoutera le retrait de votre consentement (art. 17.1.b).</p>
      </fieldset>
      )}

      <fieldset className="space-y-2">
        <legend className="text-sm font-medium text-slate-800">Comment envoyer la demande ?</legend>
        <label className={`flex items-start gap-2 text-sm ${canUseGmail ? '' : 'opacity-50'}`}>
          <input type="radio" value="gmail" disabled={!canUseGmail} className="mt-0.5 accent-indigo-600" {...register('channel')} />
          <span>Depuis mon adresse Gmail, par l'application{organization.privacy_email && ` (à ${organization.privacy_email})`}</span>
        </label>
        <label className="flex items-start gap-2 text-sm">
          <input type="radio" value="manual" className="mt-0.5 accent-indigo-600" {...register('channel')} />
          <span>Je l'envoie moi-même (formulaire du site, e-mail ou courrier), puis je confirme l'envoi</span>
        </label>
        {!canUseGmail && (
          <p className="text-xs text-slate-500">
            {organization.category === 'search_engine'
              ? 'Les moteurs de recherche traitent ces demandes via leur propre formulaire de déréférencement.'
              : 'Aucune adresse e-mail de contact n\'est connue pour cet organisme.'}
          </p>
        )}
        <FieldError message={errors.channel?.message} />
      </fieldset>

      <div className="space-y-1">
        <Checkbox label="Je confirme que cette demande concerne mes propres données personnelles." {...register('own_data_confirmed')} />
        <FieldError message={errors.own_data_confirmed?.message} />
      </div>

      {create.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(create.error)}</Alert>}

      <div className="flex justify-between">
        <Button variant="ghost" onClick={onBack}>
          ← Retour
        </Button>
        <Button type="submit" loading={create.isPending}>
          Générer la lettre
        </Button>
      </div>
    </form>
  )
}
