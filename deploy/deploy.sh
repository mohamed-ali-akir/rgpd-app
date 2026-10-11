#!/usr/bin/env bash
# Mise à jour de l'application sur le serveur (à lancer depuis la racine du dépôt, par l'utilisateur www-data
# ou un utilisateur de déploiement qui a les droits sur le dossier) :
#   bash deploy/deploy.sh
set -euo pipefail

cd "$(dirname "$0")/.."

echo "→ Récupération du code"
git pull --ff-only

echo "→ Backend"
cd backend
php artisan down --retry=30                 # page de maintenance pendant la mise à jour
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force                 # --force : obligatoire en production
php artisan config:cache                    # met en cache la configuration (plus rapide)
php artisan route:cache
php artisan view:cache
php artisan queue:restart                   # le worker relira le nouveau code après son job en cours
php artisan up
cd ..

echo "→ Frontend"
cd frontend
npm ci
npm run build                               # Nginx sert frontend/dist
cd ..

echo "✓ Déploiement terminé"
