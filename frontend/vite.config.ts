/// <reference types="vitest/config" />
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// Laravel tourne sur le port 8000 (php artisan serve).
const backend = 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    port: 5173,
    // En dev, le navigateur ne parle qu'au serveur Vite (localhost:5173), qui transmet
    // /api et /sanctum à Laravel : front et API sont donc sur le MÊME domaine, comme en
    // production derrière Nginx. C'est indispensable pour les cookies de session Sanctum.
    // changeOrigin: false garde l'en-tête Host d'origine (localhost:5173) : Laravel génère
    // alors ses liens (ex. lien signé de vérification d'e-mail) avec la bonne adresse.
    proxy: {
      '/api': { target: backend, changeOrigin: false },
      '/sanctum': { target: backend, changeOrigin: false },
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    // Remet à zéro les compteurs d'appels des fonctions factices entre deux tests.
    clearMocks: true,
  },
})
