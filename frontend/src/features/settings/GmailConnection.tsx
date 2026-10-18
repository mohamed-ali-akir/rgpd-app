import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useSearchParams } from 'react-router'
import { disconnectGoogle, GOOGLE_CONNECT_URL } from '../../api/account'
import { Alert } from '../../components/ui/Alert'
import { Button } from '../../components/ui/Button'
import { buttonClasses } from '../../components/ui/buttonClasses'
import { errorMessage } from '../../lib/errors'
import { USER_KEY, useUser } from '../auth/useAuth'
import { useIsDemo } from '../meta/useMeta'

/** Messages renvoyés par le back dans l'URL après le retour de Google (?google=...). */
const CALLBACK_MESSAGES: Record<string, { tone: 'success' | 'error' | 'warning'; text: string }> = {
  connected: { tone: 'success', text: 'Gmail est connecté.' },
  denied: { tone: 'warning', text: 'Connexion annulée.' },
  missing_scope: { tone: 'error', text: 'Autorisation incomplète : cochez bien « Envoyer des e-mails en votre nom » sur l\'écran de Google.' },
  error: { tone: 'error', text: 'La connexion à Google a échoué. Réessayez.' },
}

export function GmailConnection() {
  const { data: user } = useUser()
  const queryClient = useQueryClient()
  const [searchParams] = useSearchParams()
  const callback = CALLBACK_MESSAGES[searchParams.get('google') ?? '']
  const isDemo = useIsDemo()

  const disconnect = useMutation({
    mutationFn: () => disconnectGoogle(),
    // L'utilisateur contient google_email : on le relit pour mettre l'écran à jour.
    onSuccess: () => queryClient.invalidateQueries({ queryKey: USER_KEY }),
  })

  return (
    <div className="space-y-4 text-sm">
      {callback && <Alert tone={callback.tone}>{callback.text}</Alert>}
      <p className="text-slate-600">
        L'application demande uniquement l'autorisation d'<strong>envoyer</strong> des e-mails en votre nom (scope
        gmail.send). Elle ne peut pas lire votre boîte de réception. Vous pouvez retirer cet accès à tout moment.
      </p>
      {isDemo ? (
        <Alert tone="info">
          Démo : un compte Gmail fictif est connecté ({user?.google_email}). Les envois sont simulés, aucun e-mail ne part.
        </Alert>
      ) : user?.google_email ? (
        <div className="flex flex-wrap items-center gap-3">
          <span>
            Connecté : <strong>{user.google_email}</strong>
          </span>
          <Button variant="secondary" loading={disconnect.isPending} onClick={() => disconnect.mutate()}>
            Déconnecter Gmail
          </Button>
        </div>
      ) : (
        // Lien classique (et non axios) : le navigateur doit être redirigé vers l'écran de Google.
        <a href={GOOGLE_CONNECT_URL} className={buttonClasses('primary')}>
          Connecter Gmail
        </a>
      )}
      {disconnect.isError && <Alert tone="error">{errorMessage(disconnect.error)}</Alert>}
    </div>
  )
}
