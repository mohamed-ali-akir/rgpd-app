import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { Alert } from '../../components/ui/Alert'
import { Button } from '../../components/ui/Button'
import { InputField, SelectField } from '../../components/ui/Field'
import { applyServerErrors, errorMessage } from '../../lib/errors'
import { CATEGORIES, CATEGORY_LABELS } from '../../lib/labels'
import { organizationSchema, type OrganizationFormOutput, type OrganizationFormValues } from '../../lib/schemas'
import type { Organization } from '../../types/api'
import { useCreateOrganization } from './useOrganizations'

interface OrganizationFormProps {
  defaultDomain?: string
  onCreated: (organization: Organization) => void
}

export function OrganizationForm({ defaultDomain = '', onCreated }: OrganizationFormProps) {
  const create = useCreateOrganization()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<OrganizationFormValues, unknown, OrganizationFormOutput>({
    resolver: zodResolver(organizationSchema),
    defaultValues: { name: '', domain: defaultDomain, category: 'other', privacy_email: '', privacy_form_url: '', dpo_name: '' },
  })

  // handleSubmit transmet les valeurs APRÈS transformation par zod (champs vides → null).
  const onSubmit = handleSubmit((values) =>
    create.mutate(values, {
      onSuccess: onCreated,
      onError: (error) => applyServerErrors(error, setError),
    }),
  )

  return (
    <form onSubmit={onSubmit} noValidate className="space-y-4">
      <Alert tone="warning">
        Ne saisissez que des coordonnées trouvées sur la politique de confidentialité officielle du site (rubrique
        « Données personnelles », « RGPD » ou « Mentions légales »).
      </Alert>
      {create.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(create.error)}</Alert>}
      <div className="grid gap-4 sm:grid-cols-2">
        <InputField label="Nom de l'organisme" error={errors.name?.message} {...register('name')} />
        <InputField label="Domaine du site" placeholder="exemple.fr" error={errors.domain?.message} {...register('domain')} />
        <SelectField
          label="Catégorie"
          error={errors.category?.message}
          options={CATEGORIES.map((c) => ({ value: c, label: CATEGORY_LABELS[c] }))}
          {...register('category')}
        />
        <InputField label="E-mail RGPD / DPO (facultatif)" type="email" error={errors.privacy_email?.message} {...register('privacy_email')} />
        <InputField label="Formulaire de demande (facultatif)" type="url" placeholder="https://" error={errors.privacy_form_url?.message} {...register('privacy_form_url')} />
        <InputField label="Nom du DPO (facultatif)" error={errors.dpo_name?.message} {...register('dpo_name')} />
      </div>
      <Button type="submit" loading={create.isPending}>
        Ajouter l'organisme
      </Button>
    </form>
  )
}
