import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { Link, useLocation, useNavigate } from 'react-router'
import { Alert } from '../components/ui/Alert'
import { Button } from '../components/ui/Button'
import { InputField } from '../components/ui/Field'
import { useLogin } from '../features/auth/useAuth'
import { AuthLayout } from '../features/auth/AuthLayout'
import { applyServerErrors, errorMessage } from '../lib/errors'
import { loginSchema, type LoginValues } from '../lib/schemas'

export function LoginPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const loginMutation = useLogin()

  /*
   * react-hook-form gère l'état du formulaire (valeurs, erreurs, envoi en cours)
   * sans re-rendre le composant à chaque frappe. register('email') branche un champ,
   * handleSubmit valide avec le schéma zod puis appelle onSubmit si tout est correct.
   */
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<LoginValues>({ resolver: zodResolver(loginSchema) })

  const onSubmit = (values: LoginValues) =>
    loginMutation.mutate(values, {
      onSuccess: () => {
        const from = (location.state as { from?: string } | null)?.from ?? '/'
        navigate(from, { replace: true })
      },
      onError: (error) => applyServerErrors(error, setError),
    })

  return (
    <AuthLayout title="Connexion" footer={<>Pas encore de compte ? <Link to="/register" className="font-semibold text-indigo-600 hover:underline">Créer un compte</Link></>}>
      {/* noValidate : on laisse zod valider (messages en français), pas le navigateur. */}
      <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
        {loginMutation.isError && !errors.email && <Alert tone="error">{errorMessage(loginMutation.error)}</Alert>}
        <InputField label="Adresse e-mail" type="email" autoComplete="email" error={errors.email?.message} {...register('email')} />
        <InputField label="Mot de passe" type="password" autoComplete="current-password" error={errors.password?.message} {...register('password')} />
        <Button type="submit" loading={loginMutation.isPending} className="w-full">
          Se connecter
        </Button>
      </form>
    </AuthLayout>
  )
}
