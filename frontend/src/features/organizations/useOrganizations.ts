import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createOrganization, resolveOrganization, searchOrganizations, type OrganizationInput } from '../../api/organizations'

export function useOrganizations(search: string) {
  return useQuery({
    // La recherche fait partie de la clé : chaque texte recherché a sa propre entrée de cache.
    queryKey: ['organizations', search],
    queryFn: () => searchOrganizations(search),
    // Garde l'ancienne liste affichée pendant le chargement de la nouvelle (pas de clignotement).
    placeholderData: (previous) => previous,
  })
}

export function useResolveOrganization() {
  // Une mutation plutôt qu'une query : la résolution est déclenchée par un clic, pas à l'affichage.
  return useMutation({ mutationFn: (url: string) => resolveOrganization(url) })
}

export function useCreateOrganization() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: OrganizationInput) => createOrganization(input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['organizations'] }),
  })
}
