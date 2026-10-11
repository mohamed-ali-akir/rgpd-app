import { describe, expect, it } from 'vitest'
import { daysUntil, deadlineText } from './dates'

const now = new Date('2026-10-10T12:00:00Z')

describe('daysUntil', () => {
  it('compte les jours restants avant l\'échéance', () => {
    expect(daysUntil('2026-10-20T12:00:00Z', now)).toBe(10)
  })

  it('est négatif quand l\'échéance est dépassée', () => {
    expect(daysUntil('2026-10-07T12:00:00Z', now)).toBe(-3)
  })
})

describe('deadlineText', () => {
  it.each([
    ['2026-10-20T12:00:00Z', '10 jours restants'],
    ['2026-10-11T10:00:00Z', 'Dernier jour'],
    ['2026-10-09T12:00:00Z', 'Échéance dépassée de 1 jour'],
    ['2026-10-05T12:00:00Z', 'Échéance dépassée de 5 jours'],
  ])('%s → %s', (due, expected) => {
    expect(deadlineText(due, now)).toBe(expected)
  })

  it('renvoie null sans échéance', () => {
    expect(deadlineText(null, now)).toBeNull()
  })
})
