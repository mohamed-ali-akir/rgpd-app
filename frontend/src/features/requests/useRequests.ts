import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  closeRequest,
  createRequest,
  deleteRequest,
  fetchDashboard,
  fetchRequest,
  fetchRequests,
  markSent,
  prepareComplaint,
  recordResponse,
  sendReminder,
  sendRequest,
  updateRequest,
  type CreateRequestInput,
  type UpdateRequestInput,
} from '../../api/requests'
import type { ErasureRequestDetail, RequestStatus, ResponseOutcome } from '../../types/api'
import { countSendFailures } from './sending'

/*
 * Clés de cache hiérarchiques : invalider ['requests'] rafraîchit d'un coup
 * la liste, les listes filtrées et chaque détail (['requests', 'detail', 12]…).
 */
export const requestKeys = {
  all: ['requests'] as const,
  list: (status?: RequestStatus) => ['requests', 'list', status ?? 'all'] as const,
  detail: (id: number) => ['requests', 'detail', id] as const,
  dashboard: ['requests', 'dashboard'] as const,
}

export function useDashboard() {
  return useQuery({ queryKey: requestKeys.dashboard, queryFn: fetchDashboard })
}

export function useRequests(status?: RequestStatus) {
  return useQuery({ queryKey: requestKeys.list(status), queryFn: () => fetchRequests(status) })
}

/**
 * @param failuresAtSend si un envoi Gmail vient d'être lancé : nombre d'échecs dans l'historique
 *   à ce moment-là (sinon null). L'envoi est fait en arrière-plan par le worker : on relit la
 *   demande toutes les 2 s tant qu'elle est en brouillon ET qu'aucun nouvel échec n'est apparu.
 */
export function useRequest(id: number, failuresAtSend: number | null = null) {
  return useQuery({
    queryKey: requestKeys.detail(id),
    queryFn: () => fetchRequest(id),
    refetchInterval: (query) => {
      const request = query.state.data
      const stillSending = failuresAtSend !== null && request?.status === 'draft' && countSendFailures(request) <= failuresAtSend
      return stillSending ? 2000 : false
    },
  })
}

export function useCreateRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateRequestInput) => createRequest(input),
    onSuccess: (request) => {
      queryClient.setQueryData(requestKeys.detail(request.id), request)
      queryClient.invalidateQueries({ queryKey: requestKeys.all })
    },
  })
}

/**
 * Toutes les mutations sur une demande existante renvoient la demande à jour :
 * on la range dans le cache du détail, et on invalide les listes et le tableau de bord.
 */
function useRequestMutation<TVariables>(id: number, mutationFn: (variables: TVariables) => Promise<ErasureRequestDetail>) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn,
    onSuccess: (request) => {
      queryClient.setQueryData(requestKeys.detail(id), request)
      queryClient.invalidateQueries({ queryKey: ['requests', 'list'] })
      queryClient.invalidateQueries({ queryKey: requestKeys.dashboard })
    },
  })
}

export function useUpdateRequest(id: number) {
  return useRequestMutation(id, (input: UpdateRequestInput) => updateRequest(id, input))
}

export function useSendRequest(id: number) {
  return useRequestMutation(id, () => sendRequest(id))
}

export function useMarkSent(id: number) {
  return useRequestMutation(id, (sentAt: string) => markSent(id, sentAt))
}

export function useSendReminder(id: number) {
  return useRequestMutation(id, () => sendReminder(id))
}

export function useRecordResponse(id: number) {
  return useRequestMutation(id, ({ outcome, note }: { outcome: ResponseOutcome; note: string }) => recordResponse(id, outcome, note))
}

export function usePrepareComplaint(id: number) {
  return useRequestMutation(id, () => prepareComplaint(id))
}

export function useCloseRequest(id: number) {
  return useRequestMutation(id, () => closeRequest(id))
}

export function useDeleteRequest(id: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => deleteRequest(id),
    onSuccess: () => {
      queryClient.removeQueries({ queryKey: requestKeys.detail(id) })
      queryClient.invalidateQueries({ queryKey: requestKeys.all })
    },
  })
}
