# Mise en production (VPS Ubuntu)

Guide pour un serveur Ubuntu LTS avec Nginx, PHP-FPM et MySQL. Il faut au préalable un nom de domaine dont
l'enregistrement DNS `A` pointe vers l'adresse IP du serveur. Remplacer `efface-moi.example` par votre domaine.

Fichiers de ce dossier :

| Fichier | Rôle |
|---|---|
| `nginx.conf` | SPA à la racine, `/api` et `/sanctum` vers Laravel, sur le même domaine |
| `rgpd-queue.service` | Worker de la file d'attente (envois Gmail, scans, vérifications), relancé par systemd ; `--timeout=650` car un scan peut durer 10 min |
| `crontab` | La ligne de cron qui lance le planificateur Laravel chaque minute |
| `deploy.sh` | Mise à jour : `git pull`, dépendances, migrations, caches, build du front |
| `alwaysdata/` | Variante pour la démo publique sur l'offre gratuite d'alwaysdata, dans un sous-dossier (voir son README) |
| `../backend/.env.production.example` | Modèle du `.env` de production |

## 1. Paquets

```bash
sudo apt update
sudo apt install -y nginx mysql-server git unzip certbot python3-certbot-nginx
# PHP 8.3 minimum (voir composer.json). Si la distribution propose une version plus ancienne,
# ajouter d'abord le dépôt : sudo add-apt-repository ppa:ondrej/php && sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-gd php8.4-zip php8.4-intl
# Composer : suivre https://getcomposer.org/download/ (vérification de la signature incluse)
# Node.js 22 LTS : suivre https://nodejs.org/en/download (gestionnaire de paquets)
```

## 2. Base de données

```bash
sudo mysql
```

```sql
CREATE DATABASE rgpd_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'rgpd_app'@'localhost' IDENTIFIED BY 'un-mot-de-passe-long-et-aleatoire';
GRANT ALL PRIVILEGES ON rgpd_app.* TO 'rgpd_app'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Code et configuration

```bash
sudo mkdir -p /var/www && sudo chown $USER:www-data /var/www
git clone <url-du-depot-github> /var/www/rgpd-app
cd /var/www/rgpd-app/backend
cp .env.production.example .env        # puis remplir chaque valeur <...>
composer install --no-dev --optimize-autoloader
php artisan key:generate               # À SAUVEGARDER : sans APP_KEY, profils et jetons chiffrés sont perdus
php artisan migrate --force
php artisan db:seed --class=OrganizationSeeder --force
sudo chown -R www-data:www-data storage bootstrap/cache

cd ../frontend && npm ci && npm run build
```

## 4. Nginx et HTTPS

```bash
sudo cp /var/www/rgpd-app/deploy/nginx.conf /etc/nginx/sites-available/rgpd-app
# Adapter server_name et la version de PHP-FPM dans le fichier, puis :
sudo ln -s /etc/nginx/sites-available/rgpd-app /etc/nginx/sites-enabled/
sudo certbot --nginx -d efface-moi.example   # certificat Let's Encrypt, renouvelé automatiquement
sudo nginx -t && sudo systemctl reload nginx
```

## 5. Worker et planificateur

```bash
sudo cp /var/www/rgpd-app/deploy/rgpd-queue.service /etc/systemd/system/
sudo systemctl daemon-reload && sudo systemctl enable --now rgpd-queue
sudo crontab -u www-data /var/www/rgpd-app/deploy/crontab
```

## 6. Google OAuth en production

Dans la Google Cloud Console, ajouter l'URI de redirection `https://efface-moi.example/api/auth/google/callback`
et le domaine dans l'écran de consentement. Voir la section « Configurer Gmail » du README principal.

## 7. Recherche web (SearXNG, facultatif)

Sans cette étape, le scanner fonctionne avec le seul registre des entreprises (`SEARCH_PROVIDER=none`).
SearXNG tourne dans un conteneur Docker, accessible **uniquement depuis le serveur** (127.0.0.1) :

```bash
sudo apt install -y docker.io
sudo mkdir -p /etc/searxng
sudo tee /etc/searxng/settings.yml > /dev/null <<EOF
use_default_settings: true
server:
  secret_key: "$(openssl rand -hex 32)"
  limiter: false          # instance privée : seul Laravel l'interroge
search:
  formats: [html, json]   # l'API JSON est désactivée par défaut
EOF
sudo docker run -d --name searxng --restart unless-stopped \
  -p 127.0.0.1:8888:8080 -v /etc/searxng:/etc/searxng searxng/searxng
curl -s 'http://127.0.0.1:8888/search?q=test&format=json' | head -c 200   # doit renvoyer du JSON
```

Puis dans `backend/.env` : `SEARCH_PROVIDER=searxng` et `SEARXNG_URL=http://127.0.0.1:8888`, et enfin
`php artisan config:cache`. Le port n'est pas ouvert sur Internet : personne d'autre ne peut utiliser l'instance.

## 8. Mode démo (facultatif)

Pour une mise en ligne publique sans risque (portfolio) : `APP_DEMO=true` dans `.env`, puis `php artisan config:cache`
et `php artisan demo:reset`. Les envois et le scanner sont alors fictifs, et le compte de démo est réinitialisé chaque nuit.

## 9. Vérifications

- [ ] `https://efface-moi.example` affiche la page de connexion, `http://` redirige vers `https://`
- [ ] Inscription → l'e-mail de vérification arrive (SMTP) et le lien fonctionne
- [ ] Connexion Gmail → retour sur Paramètres avec « Gmail est connecté »
- [ ] Envoi d'une demande de test vers votre propre adresse : statut « Envoyée » après quelques secondes
- [ ] `systemctl status rgpd-queue` : actif
- [ ] `php artisan schedule:list` : les tâches planifiées apparaissent (délais, vérifications, scans mensuels, purges)
- [ ] Page « Mes données en ligne » → « Lancer ma première recherche » : le rapport s'affiche après quelques minutes
- [ ] `APP_DEBUG=false` : une URL inexistante sous `/api` renvoie une erreur JSON sans détail technique

## Mises à jour

```bash
cd /var/www/rgpd-app && bash deploy/deploy.sh
```

## Sauvegardes

Sauvegarder chaque jour la base (`mysqldump rgpd_app`) **et** la valeur de `APP_KEY` : les colonnes chiffrées
(profil, jetons Google) sont illisibles sans elle. Conserver les sauvegardes sur un autre support que le serveur.
