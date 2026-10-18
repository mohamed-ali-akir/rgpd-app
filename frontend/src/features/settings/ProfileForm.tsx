import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { fetchProfile, updateProfile } from '../../api/account'
import { Alert } from '../../components/ui/Alert'
import { Button } from '../../components/ui/Button'
import { Checkbox, InputField, TextareaField } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { applyServerErrors, errorMessage } from '../../lib/errors'
import { profileSchema, type ProfileFormOutput, type ProfileFormValues } from '../../lib/schemas'
import type { Profile } from '../../types/api'

export function ProfileForm() {
  const profile = useQuery({ queryKey: ['profile'], queryFn: fetchProfile })

  if (profile.isPending) return <Spinner />
  if (profile.isError) return <Alert tone="error">{errorMessage(profile.error)}</Alert>

  // Le formulaire n'est affiché qu'une fois le profil chargé : ses valeurs initiales sont donc connues.
  return <ProfileFormFields profile={profile.data} />
}

function ProfileFormFields({ profile }: { profile: Profile }) {
  const queryClient = useQueryClient()
  const save = useMutation({
    mutationFn: (input: ProfileFormOutput) => updateProfile(input),
    onSuccess: (saved) => queryClient.setQueryData(['profile'], saved),
  })
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<ProfileFormValues, unknown, ProfileFormOutput>({
    resolver: zodResolver(profileSchema),
    defaultValues: {
      first_name: profile.first_name,
      last_name: profile.last_name,
      postal_address: profile.postal_address ?? '',
      phone: profile.phone ?? '',
      auto_scan: profile.auto_scan,
    },
  })

  const onSubmit = handleSubmit((values) =>
    save.mutate(values, { onError: (error) => applyServerErrors(error, setError) }),
  )

  return (
    <form onSubmit={onSubmit} noValidate className="space-y-4">
      <p className="text-sm text-slate-600">
        Ces informations remplissent l'en-tête de vos lettres. L'adresse et le téléphone sont facultatifs : un organisme peut
        toutefois en avoir besoin pour vous identifier.
      </p>
      <div className="grid gap-4 sm:grid-cols-2">
        <InputField label="Prénom" error={errors.first_name?.message} {...register('first_name')} />
        <InputField label="Nom" error={errors.last_name?.message} {...register('last_name')} />
      </div>
      <TextareaField label="Adresse postale (facultatif)" rows={3} error={errors.postal_address?.message} {...register('postal_address')} />
      <InputField label="Téléphone (facultatif)" type="tel" error={errors.phone?.message} {...register('phone')} />
      <div className="space-y-1 rounded-md bg-slate-50 p-3">
        <Checkbox label="Rechercher automatiquement mes données en ligne chaque mois" {...register('auto_scan')} />
        <p className="pl-6 text-xs text-slate-500">
          Le scanner cherche votre nom, votre e-mail et votre téléphone, puis vous prévient par e-mail s'il trouve de nouvelles
          pages. Rien n'est envoyé aux organismes sans votre confirmation.
        </p>
      </div>
      {save.isSuccess && <Alert tone="success">Profil enregistré.</Alert>}
      {save.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(save.error)}</Alert>}
      <Button type="submit" loading={save.isPending}>
        Enregistrer
      </Button>
    </form>
  )
}
