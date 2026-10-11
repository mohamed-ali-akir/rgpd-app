import { PageHeader } from '../components/ui/PageHeader'
import { NewRequestWizard } from '../features/requests/wizard/NewRequestWizard'

export function NewRequestPage() {
  return (
    <>
      <PageHeader
        title="Nouvelle demande"
        description="Demandez l'effacement de données personnelles publiées sur un site (RGPD, art. 17 et 21)."
      />
      <NewRequestWizard />
    </>
  )
}
