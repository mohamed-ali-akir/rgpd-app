import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchUser, login, logout, register, resendVerificationEmail, type LoginInput, type RegisterInput } from '../../api/auth'

/*
 * TanStack Query en deux mots :
 * - useQuery LIT des données serveur. Il les met en cache sous une "clé" (ici ['user']) :
 *   tous les composants qui appellent useUser() partagent la même donnée et une seule requête HTTP.
 *   Il fournit isPending / error / data, sans useState ni useEffect à écrire soi-même.
 * - useMutation MODIFIE des données serveur (POST, PUT, DELETE). En cas de succès,
 *   on met à jour ou on invalide le cache pour que l'écran se rafraîchisse.
 */

export const USER_KEY = ['user'] as const

export function useUser() {
  return useQuery({
    queryKey: USER_KEY,
    queryFn: fetchUser,
    staleTime: 5 * 60 * 1000, // considéré "frais" 5 min : pas de nouvelle requête à chaque page
    retry: false,
  })
}

/*
 * mutationFn est enveloppée dans une fonction fléchée : TanStack Query passe un 2e argument
 * (son contexte) qu'on ne veut pas transmettre aux fonctions d'API.
 */
export function useLogin() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: LoginInput) => login(input),
    // On place directement l'utilisateur renvoyé par l'API dans le cache.
    onSuccess: (user) => queryClient.setQueryData(USER_KEY, user),
  })
}

export function useRegister() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: RegisterInput) => register(input),
    onSuccess: (user) => queryClient.setQueryData(USER_KEY, user),
  })
}

export function useLogout() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => logout(),
    onSuccess: () => {
      // On vide TOUT le cache : les données du compte précédent ne doivent pas rester en mémoire.
      queryClient.clear()
      queryClient.setQueryData(USER_KEY, null)
    },
  })
}

export function useResendVerification() {
  return useMutation({ mutationFn: () => resendVerificationEmail() })
}
