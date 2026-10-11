import { STATUS_COLORS, STATUS_LABELS } from '../../lib/labels'
import type { RequestStatus } from '../../types/api'

export function StatusBadge({ status }: { status: RequestStatus }) {
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_COLORS[status]}`}>
      {STATUS_LABELS[status]}
    </span>
  )
}
