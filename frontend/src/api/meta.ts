import type { Meta } from '../types/api'
import { api } from './client'

export async function fetchMeta(): Promise<Meta> {
  const { data } = await api.get<Meta>('/meta')
  return data
}
