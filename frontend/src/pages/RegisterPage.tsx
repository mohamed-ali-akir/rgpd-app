import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { Link, useNavigate } from 'react-router'
import { Alert } from '../components/ui/Alert'
import { Button } from '../components/ui/Button'
import { InputField } from '../components/ui/Field'
import { AuthLayout } from '../features/auth/AuthLayout'
import { useRegister } from '../features/auth/useAuth'
import { applyServerErrors, errorMessage } from '../lib/errors'
import { registerSchema, type RegisterValues } from '../lib/schemas'

export function RegisterPage() {
  const navigate = useNavigate()
  const registerMutation = useRegister()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<RegisterValues>({ resolver: zodResolver(registerSchema) })

  const onSubmit = (values: RegisterValues) =>
    registerMutation.mutate(values, {
      onSuccess: () => navigate('/', { replace: true }),
      onError: (error) => applyServerErrors(error, setError),
    })

  const serverError = registerMutation.isError && Object.keys(errors).length === 0

  return (
    <AuthLayout title="Créer un compte" footer={<>Déjà inscrit ? <Link to="/login" className="font-semibold text-indigo-600 hover:underline">Se connecter</Link></>}>
      <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
        {serverError && <Alert tone="error">{errorMessage(registerMutation.error)}</Alert>}
        <div className="grid grid-cols-2 gap-3">
          <InputField label="Prénom" autoComplete="given-name" error={errors.first_name?.message} {...register('first_name')} />
          <InputField label="Nom" autoComplete="family-name" error={errors.last_name?.message} {...register('last_name')} />
        </div>
        <InputField label="Adresse e-mail" type="email" autoComplete="email" error={errors.email?.message} {...register('email')} />
        <InputField label="Mot de passe" type="password" autoComplete="new-password" hint="10 caractères minimum." error={errors.password?.message} {...register('password')} />
        <InputField label="Confirmer le mot de passe" type="password" autoComplete="new-password" error={errors.password_confirmation?.message} {...register('password_confirmation')} />
        <p className="text-xs text-slate-500">
          Vos nom et prénom servent uniquement à signer vos demandes. Ils sont chiffrés en base.{' '}
          <Link to="/privacy" className="underline">Ce que nous faisons de vos données</Link>
        </p>
        <Button type="submit" loading={registerMutation.isPending} className="w-full">
          Créer mon compte
        </Button>
      </form>
    </AuthLayout>
  )
}
