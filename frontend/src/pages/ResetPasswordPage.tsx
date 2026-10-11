import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { resetPassword, type ResetPasswordInput } from '../api/auth'
import { Alert } from '../components/ui/Alert'
import { Button } from '../components/ui/Button'
import { InputField } from '../components/ui/Field'
import { AuthLayout } from '../features/auth/AuthLayout'
import { USER_KEY } from '../features/auth/useAuth'
import { applyServerErrors, errorMessage } from '../lib/errors'
import { resetPasswordSchema, type ResetPasswordValues } from '../lib/schemas'

/** Page ouverte depuis le lien de l'e-mail : /reset-password/{token}?email=... */
export function ResetPasswordPage() {
  const { token = '' } = useParams()
  const [searchParams] = useSearchParams()
  const email = searchParams.get('email') ?? ''
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const reset = useMutation({ mutationFn: (input: ResetPasswordInput) => resetPassword(input) })
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<ResetPasswordValues>({ resolver: zodResolver(resetPasswordSchema) })

  const onSubmit = (values: ResetPasswordValues) =>
    reset.mutate(
      { ...values, token, email },
      {
        onSuccess: (message) => {
          // Le serveur a fermé toutes les sessions : on oublie l'utilisateur côté front aussi.
          queryClient.setQueryData(USER_KEY, null)
          navigate('/login', { replace: true, state: { message } })
        },
        onError: (error) => applyServerErrors(error, setError),
      },
    )

  const footer = <Link to="/forgot-password" className="font-semibold text-indigo-600 hover:underline">Demander un nouveau lien</Link>

  if (!token || !email) {
    return (
      <AuthLayout title="Lien incomplet" footer={footer}>
        <Alert tone="error">Ce lien de réinitialisation est incomplet. Copiez-le en entier depuis l'e-mail, ou demandez-en un nouveau.</Alert>
      </AuthLayout>
    )
  }

  return (
    <AuthLayout title="Nouveau mot de passe" footer={footer}>
      <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
        <p className="text-sm text-slate-600">
          Compte : <strong>{email}</strong>
        </p>
        {/* Jeton invalide ou expiré : l'API renvoie l'erreur sur "email", qui n'est pas un champ de ce formulaire. */}
        {reset.isError && !errors.password && !errors.password_confirmation && <Alert tone="error">{errorMessage(reset.error)}</Alert>}
        <InputField label="Nouveau mot de passe" type="password" autoComplete="new-password" hint="10 caractères minimum." error={errors.password?.message} {...register('password')} />
        <InputField label="Confirmer le mot de passe" type="password" autoComplete="new-password" error={errors.password_confirmation?.message} {...register('password_confirmation')} />
        <Button type="submit" loading={reset.isPending} className="w-full">
          Enregistrer le nouveau mot de passe
        </Button>
      </form>
    </AuthLayout>
  )
}
