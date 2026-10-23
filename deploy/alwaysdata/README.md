# Démo en ligne sur alwaysdata (offre gratuite)

La démo publique tourne sur **https://akir.alwaysdata.net/efface-moi**, à côté de StagePilot, servi à la
racine du même compte. Elle utilise le mode démo (`APP_DEMO=true`) : compte partagé, données fictives,
aucun e-mail ni recherche réelle, réinitialisation chaque nuit.

L'offre gratuite (1 Go de disque, 256 Mo de RAM, 0,25 CPU) impose trois choix :

| Contrainte | Choix |
|---|---|
| Pas de service permanent (donc pas de `queue:work`) | `QUEUE_CONNECTION=sync` : les jobs s'exécutent pendant la requête ; en démo ils sont instantanés |
| Une seule adresse `akir.alwaysdata.net`, déjà prise par StagePilot | Un 2ᵉ site sur le chemin `/efface-moi` |
| Peu de CPU | Le front est construit sur le PC ; SQLite au lieu de MySQL |

Fichiers de ce dossier :

| Fichier | Rôle |
|---|---|
| `deploy.sh` | Construit le front pour `/efface-moi/`, envoie le code en SSH, installe les dépendances, migre, met en cache |
| `.htaccess` | Remplace celui de Laravel : `/api` et `/sanctum` vers `index.php`, le reste vers `index.html` (React) |
| `.env.demo.example` | Le `.env` de la démo, copié automatiquement au premier déploiement |

## Comment fonctionne le chemin `/efface-moi`

1. alwaysdata génère un seul VirtualHost Apache pour les deux sites : StagePilot en `DocumentRoot`, et
   `Alias /efface-moi "/home/akir/efface-moi/public/"`. Apache doit donc recevoir l'adresse complète
   (`/efface-moi/api/me`) : l'option « Exclure le chemin » **ne doit pas** être cochée, sinon le chemin est retiré
   avant Apache et c'est StagePilot qui répond.
2. Le `.htaccess` (avec `RewriteBase /efface-moi/`) envoie `api/...` et `sanctum/...` à `index.php`. Laravel déduit
   le sous-dossier de `SCRIPT_NAME` et ses routes restent `/api/...`.
3. Dans l'autre sens, ce qui part vers le navigateur doit contenir le chemin :
   - le front est construit avec `--base=/efface-moi/` (fichiers JS/CSS, routeur React, appels à l'API : `src/lib/basePath.ts`) ;
   - Laravel génère ses liens à partir d'`APP_URL`, qui contient le chemin (`AppServiceProvider`) ;
   - les cookies sont limités au chemin `/efface-moi` (`SESSION_PATH`) et ont leur propre nom (`SESSION_COOKIE`),
     pour ne pas se mélanger avec ceux de StagePilot.

En développement et sur un VPS (racine du domaine), rien ne change : la base vaut `/`.

## Mise en place (une seule fois)

Dans l'administration alwaysdata :

1. **Accès distant → SSH** : activer la connexion SSH pour l'utilisateur `akir` et ajouter sa clé publique
   (sinon le script demandera le mot de passe à chaque connexion).
2. **Web → Sites → Ajouter un site** :
   - Adresses : `akir.alwaysdata.net/efface-moi`
   - Type : PHP, version 8.4
   - Répertoire racine : `efface-moi/public`
   - Onglet SSL : cocher « Forcer le HTTPS »
   - Onglet Avancé : laisser **« Exclure le chemin » décoché** (voir plus haut)
3. **Avancé → Tâches planifiées → Ajouter** : commande `cd ~/efface-moi && php artisan demo:reset`, tous les jours à 3 h.
   (Le planificateur de Laravel n'est pas utilisé : la tâche StagePilot tourne toutes les 10 minutes et la démo n'a besoin
   que de cette remise à zéro.)

## Déployer

Depuis la racine du dépôt, après avoir commité :

```bash
bash deploy/alwaysdata/deploy.sh
```

Le premier déploiement crée `.env` à partir de `.env.demo.example`, génère la clé, crée la base SQLite et les
données de démonstration. Les suivants conservent `.env`, `database/database.sqlite` et `storage/`.

Variables facultatives : `SSH_TARGET` (par défaut `akir@ssh-akir.alwaysdata.net`) et `REMOTE_DIR` (par défaut `efface-moi`).

## Vérifier

- https://akir.alwaysdata.net/efface-moi : la page de connexion affiche le bouton « Essayer la démo ».
- https://akir.alwaysdata.net/efface-moi/up : contrôle de santé de Laravel.
- https://akir.alwaysdata.net (StagePilot) fonctionne toujours.
- En cas d'erreur 500 : `~/efface-moi/storage/logs/` (en SSH).
