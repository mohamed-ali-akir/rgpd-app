import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { Alert } from '../../../components/ui/Alert'
import { Button } from '../../../components/ui/Button'
import { InputField } from '../../../components/ui/Field'
import { errorMessage } from '../../../lib/errors'
import { urlStepSchema, type UrlStepValues } from '../../../lib/schemas'
import type { ResolveResult } from '../../../types/api'
import { useResolveOrganization } from '../../organizations/useOrganizations'

interface UrlStepProps {
  defaultUrl: string
  onNext: (url: string, result: ResolveResult) => void
}

/** Étape 1 : l'utilisateur colle l'adresse de la page ; on identifie l'organisme. */
export function UrlStep({ defaultUrl, onNext }: UrlStepProps) {
  const resolve = useResolveOrganization()
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<UrlStepValues>({ resolver: zodResolver(urlStepSchema), defaultValues: { url: defaultUrl } })

  const onSubmit = ({ url }: UrlStepValues) => resolve.mutate(url, { onSuccess: (result) => onNext(url, result) })

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
      <InputField
        label="Adresse de la page où apparaissent vos données"
        type="url"
        placeholder="https://www.exemple.fr/profil/..."
        hint="Copiez l'adresse complète depuis la barre d'adresse de votre navigateur."
        error={errors.url?.message}
        {...register('url')}
      />
      {resolve.isError && <Alert tone="error">{errorMessage(resolve.error)}</Alert>}
      <div className="flex justify-end">
        <Button type="submit" loading={resolve.isPending}>
          Suivant
        </Button>
      </div>
    </form>
  )
}
