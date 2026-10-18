import { useQuery } from '@tanstack/react-query'
import { fetchMeta } from '../../api/meta'

/** Mode démo et identifiants de démonstration : ne change pas pendant la visite, lu une seule fois. */
export function useMeta() {
  return useQuery({ queryKey: ['meta'], queryFn: fetchMeta, staleTime: Infinity, retry: 1 })
}

/** Raccourci : true seulement quand le serveur a confirmé le mode démo. */
export function useIsDemo(): boolean {
  return useMeta().data?.demo === true
}
