import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router'
import { forgotPassword } from '../api/auth'
import { Alert } from '../components/ui/Alert'
import { Button } from '../components/ui/Button'
import { InputField } from '../components/ui/Field'
import { AuthLayout } from '../features/auth/AuthLayout'
import { applyServerErrors, errorMessage } from '../lib/errors'
import { forgotPasswordSchema, type ForgotPasswordValues } from '../lib/schemas'

export function ForgotPasswordPage() {
  const send = useMutation({ mutationFn: (email: string) => forgotPassword(email) })
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<ForgotPasswordValues>({ resolver: zodResolver(forgotPasswordSchema) })

  const onSubmit = ({ email }: ForgotPasswordValues) => send.mutate(email, { onError: (error) => applyServerErrors(error, setError) })

  return (
    <AuthLayout title="Mot de passe oublié" footer={<Link to="/login" className="font-semibold text-indigo-600 hover:underline">Retour à la connexion</Link>}>
      {send.isSuccess ? (
        // Le message du serveur est volontairement le même que l'adresse ait un compte ou non.
        <Alert tone="success">
          {send.data} Pensez à vérifier vos courriers indésirables. Le lien est valable 60 minutes.
        </Alert>
      ) : (
        <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
          <p className="text-sm text-slate-600">Indiquez l'adresse de votre compte : nous vous enverrons un lien pour choisir un nouveau mot de passe.</p>
          {send.isError && !errors.email && <Alert tone="error">{errorMessage(send.error)}</Alert>}
          <InputField label="Adresse e-mail" type="email" autoComplete="email" error={errors.email?.message} {...register('email')} />
          <Button type="submit" loading={send.isPending} className="w-full">
            Envoyer le lien
          </Button>
        </form>
      )}
    </AuthLayout>
  )
}
