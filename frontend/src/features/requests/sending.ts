import type { ErasureRequestDetail } from '../../types/api'

/** Nombre d'échecs d'envoi dans l'historique (sert à détecter un nouvel échec du worker). */
export function countSendFailures(request: ErasureRequestDetail | undefined): number {
  return request?.events.filter((e) => e.type === 'send_failed').length ?? 0
}

/** État transmis à la page de détail juste après un envoi lancé depuis l'assistant. */
export interface SendNavigationState {
  failuresAtSend: number
}
