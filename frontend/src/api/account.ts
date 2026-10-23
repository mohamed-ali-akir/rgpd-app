import type { Profile } from '../types/api'
import { apiPath } from '../lib/basePath'
import { api } from './client'

export async function fetchProfile(): Promise<Profile> {
  const { data } = await api.get<Profile>('/profile')
  return data
}

export async function updateProfile(input: Partial<Profile> & Pick<Profile, 'first_name' | 'last_name'>): Promise<Profile> {
  const { data } = await api.put<Profile>('/profile', input)
  return data
}

export async function disconnectGoogle(): Promise<void> {
  await api.delete('/google-account')
}

export async function deleteAccount(password: string): Promise<void> {
  await api.delete('/me', { data: { password } })
}

/*
 * Ces deux URL sont ouvertes par le navigateur lui-même (navigation ou téléchargement),
 * pas par axios : elles redirigent vers Google ou renvoient un fichier.
 */
export const GOOGLE_CONNECT_URL = apiPath('/api/auth/google/redirect')
export const EXPORT_URL = apiPath('/api/me/export')
