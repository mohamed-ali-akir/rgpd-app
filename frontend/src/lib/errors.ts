import { isAxiosError } from 'axios'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import type { ValidationErrorBody } from '../types/api'

/** Message lisible pour n'importe quelle erreur d'appel à l'API. */
export function errorMessage(error: unknown): string {
  if (!isAxiosError<ValidationErrorBody>(error)) {
    return 'Une erreur inattendue est survenue.'
  }
  if (!error.response) {
    return 'Impossible de joindre le serveur. Vérifiez votre connexion.'
  }
  if (error.response.status === 401) {
    return 'Votre session a expiré. Reconnectez-vous.'
  }
  // 502/503/504 : le proxy (Vite en dev, Nginx en prod) n'a pas pu joindre Laravel.
  if ([502, 503, 504].includes(error.response.status)) {
    return 'Le serveur ne répond pas pour le moment. Réessayez dans quelques instants.'
  }
  return error.response.data?.message || `Erreur ${error.response.status}.`
}

/**
 * Recopie les erreurs de validation Laravel (HTTP 422) sous les champs du formulaire.
 * Renvoie true si au moins une erreur a été placée sur un champ.
 */
export function applyServerErrors<T extends FieldValues>(error: unknown, setError: UseFormSetError<T>): boolean {
  if (!isAxiosError<ValidationErrorBody>(error) || error.response?.status !== 422) {
    return false
  }
  const errors = error.response.data?.errors ?? {}
  for (const [field, messages] of Object.entries(errors)) {
    setError(field as Path<T>, { type: 'server', message: messages[0] })
  }
  return Object.keys(errors).length > 0
}
