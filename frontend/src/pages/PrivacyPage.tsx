import { Card } from '../components/ui/Card'
import { PageHeader } from '../components/ui/PageHeader'

/** « Ce que nous faisons de vos données » : le RGPD appliqué à l'application elle-même. */
export function PrivacyPage() {
  return (
    <>
      <PageHeader title="Ce que nous faisons de vos données" description="Une application RGPD se doit d'être exemplaire avec vos propres données." />
      <Card>
        <div className="space-y-5 text-sm leading-relaxed text-slate-700">
          <section>
            <h2 className="font-semibold text-slate-900">Ce que nous conservons</h2>
            <ul className="mt-2 list-disc space-y-1 pl-5">
              <li>Votre compte : nom, adresse e-mail, mot de passe (haché, jamais lisible).</li>
              <li>Votre identité pour les lettres : prénom et nom, et si vous le souhaitez adresse postale et téléphone (facultatifs).</li>
              <li>Vos demandes : adresse de la page concernée, types de données, texte de la lettre et historique.</li>
              <li>Si vous connectez Gmail : votre adresse Gmail et un jeton d'autorisation limité à l'envoi d'e-mails.</li>
            </ul>
          </section>
          <section>
            <h2 className="font-semibold text-slate-900">Comment nous les protégeons</h2>
            <ul className="mt-2 list-disc space-y-1 pl-5">
              <li>Votre profil et le jeton Google sont chiffrés en base de données.</li>
              <li>L'accès Gmail est limité à l'envoi : l'application ne lit jamais votre boîte de réception.</li>
              <li>Aucune donnée personnelle n'est écrite dans les journaux techniques.</li>
              <li>La connexion utilise un cookie de session sécurisé, sans jeton stocké dans le navigateur.</li>
            </ul>
          </section>
          <section>
            <h2 className="font-semibold text-slate-900">Combien de temps</h2>
            <p className="mt-2">
              Les demandes clôturées sont supprimées automatiquement 12 mois après leur clôture. Le reste est conservé tant
              que votre compte existe.
            </p>
          </section>
          <section>
            <h2 className="font-semibold text-slate-900">Vos droits</h2>
            <ul className="mt-2 list-disc space-y-1 pl-5">
              <li>Accès et portabilité : « Exporter mes données » dans les paramètres (fichier JSON).</li>
              <li>Rectification : modifiez votre profil à tout moment.</li>
              <li>Effacement : « Supprimer mon compte » efface immédiatement tout votre contenu et révoque l'accès Gmail.</li>
              <li>Vous pouvez aussi introduire une réclamation auprès de la CNIL.</li>
            </ul>
          </section>
          <section>
            <h2 className="font-semibold text-slate-900">Ce que nous ne faisons pas</h2>
            <p className="mt-2">
              Pas de publicité, pas de revente, pas d'analyse par intelligence artificielle : les lettres sont construites à
              partir de modèles fixes. Cette application n'est pas un service de conseil juridique.
            </p>
          </section>
        </div>
      </Card>
    </>
  )
}
