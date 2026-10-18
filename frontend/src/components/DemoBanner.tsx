import { useIsDemo } from '../features/meta/useMeta'

/** Bandeau visible sur toutes les pages en mode démo : aucune action n'a d'effet réel. */
export function DemoBanner() {
  if (!useIsDemo()) return null

  return (
    <div className="bg-indigo-700 px-4 py-2 text-center text-sm text-white" role="note">
      Version de démonstration : les données sont fictives, aucun e-mail n'est envoyé et tout est réinitialisé chaque nuit.
    </div>
  )
}
