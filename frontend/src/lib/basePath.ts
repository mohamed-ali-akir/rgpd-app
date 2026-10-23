/*
 * Chemin sous lequel l'application est servie : "" à la racine d'un domaine (développement, VPS),
 * "/efface-moi" pour la démo hébergée sur akir.alwaysdata.net/efface-moi.
 * Vite le fournit dans import.meta.env.BASE_URL (option --base du build), toujours terminé par "/".
 */
export const BASE_PATH = import.meta.env.BASE_URL.replace(/\/$/, '')

/** URL d'une route de l'API ou de Sanctum, préfixée par le chemin de l'application : apiPath('/api/me'). */
export function apiPath(path: string): string {
  return BASE_PATH + path
}
