import { Link } from 'react-router'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { deadlineText, formatDate } from '../../lib/dates'
import type { ErasureRequest } from '../../types/api'

interface RequestListProps {
  requests: ErasureRequest[]
  emptyText: string
}

export function RequestList({ requests, emptyText }: RequestListProps) {
  if (requests.length === 0) {
    return <p className="py-6 text-center text-sm text-slate-500">{emptyText}</p>
  }

  return (
    <ul className="divide-y divide-slate-100">
      {/* key : identifiant stable qui permet à React de savoir quel élément a changé dans la liste. */}
      {requests.map((request) => {
        const waiting = request.status === 'sent' || request.status === 'extended'
        return (
          <li key={request.id}>
            <Link to={`/requests/${request.id}`} className="flex flex-wrap items-center justify-between gap-2 px-1 py-3 hover:bg-slate-50">
              <div className="min-w-0">
                <p className="font-medium text-slate-900">{request.organization?.name ?? 'Organisme'}</p>
                <p className="truncate text-xs text-slate-500">{request.url}</p>
              </div>
              <div className="flex items-center gap-3 text-xs text-slate-500">
                {waiting && request.due_at && <span>{deadlineText(request.due_at)}</span>}
                {request.status === 'draft' && <span>Créée le {formatDate(request.created_at)}</span>}
                <StatusBadge status={request.status} />
              </div>
            </Link>
          </li>
        )
      })}
    </ul>
  )
}
