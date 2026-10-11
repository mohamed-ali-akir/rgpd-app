import type { ReactNode } from 'react'
import type { Organization } from '../../types/api'

interface OrganizationCardProps {
  organization: Organization
  actions?: ReactNode
}

/** Résumé d'un organisme : catégorie, contacts RGPD et niveau de confiance. */
export function OrganizationCard({ organization, actions }: OrganizationCardProps) {
  return (
    <div className="rounded-md p-4 ring-1 ring-slate-200">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="font-semibold text-slate-900">{organization.name}</p>
          <p className="text-xs text-slate-500">
            {organization.domain} · {organization.category_label}
          </p>
        </div>
        {organization.verified ? (
          <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Contacts vérifiés</span>
        ) : (
          <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">
            {organization.user_created ? 'Ajouté par un utilisateur' : 'Contacts non vérifiés'}
          </span>
        )}
      </div>
      <dl className="mt-3 grid gap-1 text-sm">
        <div className="flex gap-2">
          <dt className="text-slate-500">E-mail RGPD :</dt>
          <dd className="text-slate-800">{organization.privacy_email ?? 'non renseigné'}</dd>
        </div>
        {organization.privacy_form_url && (
          <div className="flex gap-2">
            <dt className="text-slate-500">Formulaire :</dt>
            <dd>
              {/* rel="noreferrer" : le site externe ne voit pas de quelle page on vient. */}
              <a href={organization.privacy_form_url} target="_blank" rel="noreferrer" className="break-all text-indigo-600 underline">
                {organization.privacy_form_url}
              </a>
            </dd>
          </div>
        )}
        {organization.dpo_name && (
          <div className="flex gap-2">
            <dt className="text-slate-500">DPO :</dt>
            <dd className="text-slate-800">{organization.dpo_name}</dd>
          </div>
        )}
      </dl>
      {actions && <div className="mt-3 flex flex-wrap gap-2">{actions}</div>}
    </div>
  )
}
