import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'

/**
 * Instance axios partagée par tous les appels à l'API.
 *
 * Authentification Sanctum en mode SPA : pas de jeton à stocker. Le navigateur envoie
 * tout seul le cookie de session (withCredentials), et axios recopie le cookie
 * XSRF-TOKEN dans l'en-tête X-XSRF-TOKEN (withXSRFToken) pour la protection CSRF.
 */
export const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json', // Laravel répond en JSON, y compris pour les erreurs
    'X-Requested-With': 'XMLHttpRequest',
  },
})

/**
 * Demande à Laravel de déposer le cookie XSRF-TOKEN. À appeler avant la connexion
 * ou l'inscription (aucun cookie n'existe encore à ce moment-là).
 */
export async function ensureCsrfCookie(): Promise<void> {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

type RetriableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean }

/**
 * Intercepteur : si le jeton CSRF a expiré (HTTP 419), on en redemande un
 * puis on rejoue la requête une seule fois.
 */
api.interceptors.response.use(undefined, async (error: AxiosError) => {
  const config = error.config as RetriableConfig | undefined
  if (error.response?.status === 419 && config && !config._csrfRetried) {
    config._csrfRetried = true
    await ensureCsrfCookie()
    return api.request(config)
  }
  return Promise.reject(error)
})
