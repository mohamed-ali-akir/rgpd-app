import { useState } from 'react'
import { Alert } from '../components/ui/Alert'
import { Button } from '../components/ui/Button'
import { Card } from '../components/ui/Card'
import { PageHeader } from '../components/ui/PageHeader'
import { OrganizationForm } from '../features/organizations/OrganizationForm'
import { OrganizationSearch } from '../features/organizations/OrganizationSearch'
import type { Organization } from '../types/api'

export function OrganizationsPage() {
  const [showForm, setShowForm] = useState(false)
  const [created, setCreated] = useState<Organization | null>(null)

  return (
    <>
      <PageHeader
        title="Organismes"
        description="Sites et responsables de traitement connus de l'application."
        actions={
          !showForm && (
            <Button variant="secondary" onClick={() => setShowForm(true)}>
              Ajouter un organisme
            </Button>
          )
        }
      />
      <div className="space-y-6">
        {created && <Alert tone="success">{created.name} a été ajouté.</Alert>}
        {showForm && (
          <Card title="Nouvel organisme" actions={<Button variant="ghost" onClick={() => setShowForm(false)}>Annuler</Button>}>
            <OrganizationForm
              onCreated={(organization) => {
                setCreated(organization)
                setShowForm(false)
              }}
            />
          </Card>
        )}
        <Card>
          <OrganizationSearch />
        </Card>
      </div>
    </>
  )
}
