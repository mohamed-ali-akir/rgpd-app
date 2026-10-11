/**
 * Avertissement obligatoire : l'app aide à exercer ses droits, elle ne donne pas de conseil juridique.
 */
export function LegalDisclaimer({ compact = false }: { compact?: boolean }) {
  if (compact) {
    return (
      <p className="text-xs text-slate-500">
        Cette application n'est pas un service de conseil juridique : elle vous aide à exercer vos droits prévus par le
        RGPD. En cas de doute, renseignez-vous auprès de la CNIL ou d'un professionnel du droit.
      </p>
    )
  }

  return (
    <div className="rounded-md bg-slate-100 p-3 text-xs text-slate-600 ring-1 ring-slate-200 ring-inset" role="note">
      <strong className="font-semibold">Pas de conseil juridique.</strong> Les fondements et avertissements proposés
      reposent sur des règles générales et ne remplacent pas l'avis d'un professionnel. Vous restez responsable du
      contenu de votre demande, que vous pouvez modifier avant l'envoi.
    </div>
  )
}
