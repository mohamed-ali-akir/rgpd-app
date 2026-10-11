import { Link } from 'react-router'
import { EXPORT_URL } from '../api/account'
import { buttonClasses } from '../components/ui/buttonClasses'
import { Card } from '../components/ui/Card'
import { PageHeader } from '../components/ui/PageHeader'
import { DeleteAccount } from '../features/settings/DeleteAccount'
import { GmailConnection } from '../features/settings/GmailConnection'
import { ProfileForm } from '../features/settings/ProfileForm'

export function SettingsPage() {
  return (
    <>
      <PageHeader title="Paramètres" />
      <div className="space-y-6">
        <Card title="Mon identité">
          <ProfileForm />
        </Card>
        <Card title="Connexion Gmail">
          <GmailConnection />
        </Card>
        <Card title="Mes données">
          <div className="space-y-3 text-sm text-slate-600">
            <p>
              Téléchargez toutes les données que l'application conserve sur vous, au format JSON (droits d'accès et de
              portabilité, art. 15 et 20 du RGPD). <Link to="/privacy" className="text-indigo-600 underline">En savoir plus</Link>
            </p>
            <a href={EXPORT_URL} className={buttonClasses('secondary')}>
              Exporter mes données
            </a>
          </div>
        </Card>
        <Card title="Supprimer mon compte" className="ring-red-200">
          <DeleteAccount />
        </Card>
      </div>
    </>
  )
}
