import { isAxiosError } from 'axios'
import type { User } from '../types/api'
import { api, ensureCsrfCookie } from './client'

export interface LoginInput {
  email: string
  password: string
}

export interface RegisterInput {
  first_name: string
  last_name: string
  email: string
  password: string
  password_confirmation: string
}

/** Utilisateur connecté, ou null si personne ne l'est (HTTP 401). */
export async function fetchUser(): Promise<User | null> {
  try {
    const { data } = await api.get<User>('/user')
    return data
  } catch (error) {
    if (isAxiosError(error) && error.response?.status === 401) {
      return null
    }
    throw error
  }
}

export async function login(input: LoginInput): Promise<User> {
  await ensureCsrfCookie()
  const { data } = await api.post<User>('/login', input)
  return data
}

export async function register(input: RegisterInput): Promise<User> {
  await ensureCsrfCookie()
  const { data } = await api.post<User>('/register', input)
  return data
}

export async function logout(): Promise<void> {
  await api.post('/logout')
}

export async function resendVerificationEmail(): Promise<void> {
  await api.post('/email/verification-notification')
}
