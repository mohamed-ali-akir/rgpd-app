import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach } from 'vitest'

// Ajoute les vérifications DOM (toBeInTheDocument, toHaveValue…) à expect(),
// et démonte les composants après chaque test pour repartir d'une page vide.
afterEach(() => {
  cleanup()
})
