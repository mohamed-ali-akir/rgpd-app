import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createRequestsFromFindings, decideFinding, fetchFindings, fetchGuidedChecks, fetchScans, startScan } from '../../api/scanner'
import type { Finding, FindingStatus } from '../../types/api'
import { requestKeys } from '../requests/useRequests'

export const scannerKeys = {
  scans: ['scanner', 'scans'] as const,
  findings: ['scanner', 'findings'] as const,
  guided: ['scanner', 'guided'] as const,
}

/**
 * Historique des recherches. Tant que la plus récente est en cours, on la relit toutes les 3 s ;
 * à la fin, on recharge aussi les résultats.
 */
export function useScans() {
  const queryClient = useQueryClient()
  return useQuery({
    queryKey: scannerKeys.scans,
    queryFn: async () => {
      const scans = await fetchScans()
      if (scans[0] && !['queued', 'running'].includes(scans[0].status)) {
        void queryClient.invalidateQueries({ queryKey: scannerKeys.findings })
      }
      return scans
    },
    refetchInterval: (query) => {
      const latest = query.state.data?.[0]
      return latest && (latest.status === 'queued' || latest.status === 'running') ? 3000 : false
    },
  })
}

export function useStartScan() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => startScan(),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: scannerKeys.scans }),
  })
}

export function useFindings() {
  return useQuery({ queryKey: scannerKeys.findings, queryFn: fetchFindings })
}

/**
 * Décision "c'est moi / pas moi / ignorer". Mise à jour optimiste : l'écran change tout de suite,
 * et revient en arrière si le serveur refuse.
 */
export function useDecideFinding() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, status }: { id: number; status: FindingStatus }) => decideFinding(id, status),
    onMutate: async ({ id, status }) => {
      await queryClient.cancelQueries({ queryKey: scannerKeys.findings })
      const previous = queryClient.getQueryData<Finding[]>(scannerKeys.findings)
      queryClient.setQueryData<Finding[]>(scannerKeys.findings, (findings) =>
        findings?.map((finding) => (finding.id === id ? { ...finding, status } : finding)),
      )
      return { previous }
    },
    onError: (_error, _variables, context) => {
      queryClient.setQueryData(scannerKeys.findings, context?.previous)
    },
    onSettled: () => queryClient.invalidateQueries({ queryKey: scannerKeys.findings }),
  })
}

export function useCreateRequestsFromFindings() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (findingIds: number[]) => createRequestsFromFindings(findingIds),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: scannerKeys.findings })
      void queryClient.invalidateQueries({ queryKey: requestKeys.all })
    },
  })
}

export function useGuidedChecks() {
  return useQuery({ queryKey: scannerKeys.guided, queryFn: fetchGuidedChecks, staleTime: 5 * 60 * 1000 })
}
