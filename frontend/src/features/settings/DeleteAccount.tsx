import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router'
import { deleteAccount } from '../../api/account'
import { Alert } from '../../components/ui/Alert'
import { Button } from '../../components/ui/Button'
import { InputField } from '../../components/ui/Field'
import { errorMessage } from '../../lib/errors'
import { USER_KEY } from '../auth/useAuth'

export function DeleteAccount() {
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [password, setPassword] = useState('')
  const remove = useMutation({
    mutationFn: (password: string) => deleteAccount(password),
    onSuccess: () => {
      queryClient.clear()
      queryClient.setQueryData(USER_KEY, null)
      navigate('/login', { replace: true })
    },
  })

  const submit = (event: FormEvent) => {
    event.preventDefault()
    if (window.confirm('Supprimer définitivement votre compte et toutes vos demandes ? Cette action est irréversible.')) {
      remove.mutate(password)
    }
  }

  return (
    <form onSubmit={submit} className="space-y-3 text-sm">
      <p className="text-slate-600">
        Supprime immédiatement votre compte, votre profil, toutes vos demandes et leur historique, et révoque l'accès Gmail.
        Pensez à exporter vos données avant si vous souhaitez les conserver.
      </p>
      <div className="max-w-xs">
        <InputField label="Confirmez avec votre mot de passe" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} />
      </div>
      {remove.isError && <Alert tone="error">{errorMessage(remove.error)}</Alert>}
      <Button type="submit" variant="danger" disabled={!password} loading={remove.isPending}>
        Supprimer mon compte
      </Button>
    </form>
  )
}
