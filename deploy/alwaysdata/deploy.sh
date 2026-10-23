#!/usr/bin/env bash
# Déploie la démo sur akir.alwaysdata.net/efface-moi.
# À lancer depuis la racine du dépôt, dans Git Bash (Windows) ou un terminal Linux :
#   bash deploy/alwaysdata/deploy.sh
#
# Le front est construit sur ce PC (le serveur gratuit n'a que 0,25 CPU), le reste se fait en SSH.
# Le premier déploiement crée aussi le .env de démo, la clé, la base SQLite et les données fictives.
set -euo pipefail

SSH_TARGET="${SSH_TARGET:-akir@ssh-akir.alwaysdata.net}"
REMOTE_DIR="${REMOTE_DIR:-efface-moi}"   # relatif au dossier personnel (/home/akir/efface-moi)
BASE_PATH="/efface-moi/"

cd "$(dirname "$0")/../.."

# Le back envoyé est celui du dernier commit : le front doit venir du même état.
if [ -n "$(git status --porcelain -- backend frontend deploy)" ]; then
  echo "Des modifications ne sont pas commitées (backend, frontend ou deploy) : commitez-les avant de déployer." >&2
  exit 1
fi

BUILD="$(mktemp -d)"
trap 'rm -rf "$BUILD"' EXIT

echo "1/4 Code du back (dernier commit, sans .env, base ni logs)"
git archive HEAD backend | tar -x -C "$BUILD"
cp deploy/alwaysdata/.env.demo.example "$BUILD/backend/.env.demo.example"

echo "2/4 Build du front pour $BASE_PATH"
# MSYS2_ARG_CONV_EXCL : sinon Git Bash transforme "/efface-moi/" en chemin Windows (C:/Program Files/Git/...).
(cd frontend && MSYS2_ARG_CONV_EXCL="--base=" npm run build -- --base="$BASE_PATH" --outDir "$BUILD/dist" --emptyOutDir)
# Le build React et index.php de Laravel partagent le dossier public/ ; le .htaccess aiguille les requêtes.
cp -r "$BUILD/dist/." "$BUILD/backend/public/"
cp deploy/alwaysdata/.htaccess "$BUILD/backend/public/.htaccess"

echo "3/4 Envoi sur $SSH_TARGET:$REMOTE_DIR (.env, database.sqlite et storage/ sont conservés)"
# Les anciens fichiers du build ont une empreinte dans leur nom : on les retire pour ne pas les accumuler.
tar -C "$BUILD/backend" -czf - . \
  | ssh "$SSH_TARGET" "mkdir -p $REMOTE_DIR && rm -rf $REMOTE_DIR/public/assets && tar -xzf - -C $REMOTE_DIR"

echo "4/4 Dépendances, base et caches sur le serveur"
ssh "$SSH_TARGET" bash -s <<EOF
set -euo pipefail
cd "$REMOTE_DIR"
first_install=0
if [ ! -f .env ]; then
  cp .env.demo.example .env
  first_install=1
fi
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
if [ "\$first_install" = 1 ]; then
  php artisan key:generate --force
fi
touch database/database.sqlite
php artisan migrate --force
if [ "\$first_install" = 1 ]; then
  php artisan demo:reset
fi
php artisan optimize
EOF

echo "Démo à jour : https://akir.alwaysdata.net/efface-moi"
