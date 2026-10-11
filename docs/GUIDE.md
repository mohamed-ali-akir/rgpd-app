# Guide d'étude du projet (préparation E6)

Ce guide suit les jalons du cahier des charges. Pour chacun : les fichiers à lire **dans l'ordre**, les notions à
maîtriser, et des questions qu'un jury peut poser (avec des éléments de réponse). Les fichiers sont commentés : lis
les commentaires, ils expliquent le *pourquoi*.

Conseil : pour chaque fichier, essaie d'expliquer à voix haute ce que fait chaque bloc avant de lire le commentaire.

---

## Jalon 1 — Monorepo, Sanctum SPA, connexion de bout en bout, CI

**À lire**
1. `backend/bootstrap/app.php` : `statefulApi()`, rendu des erreurs en JSON, exceptions personnalisées.
2. `backend/routes/api.php` puis `backend/routes/web.php` (lis le grand commentaire de `web.php`).
3. `backend/app/Http/Controllers/Api/AuthController.php`, `RegisterRequest.php`, `LoginRequest.php`.
4. `backend/app/Http/Controllers/Api/EmailVerificationController.php`, `app/Notifications/VerifyEmail.php`.
5. `frontend/vite.config.ts` (le proxy), `frontend/src/api/client.ts`, `frontend/src/api/auth.ts`.
6. `frontend/src/main.tsx`, `App.tsx`, `features/auth/useAuth.ts`, `features/auth/RouteGuards.tsx`, `pages/LoginPage.tsx`.
7. `.github/workflows/ci.yml`.

**Notions**
- *Sanctum en mode SPA* : ce n'est pas un jeton, c'est une **session Laravel classique**. Le navigateur garde un
  cookie de session (`HttpOnly`, inaccessible au JavaScript) et un cookie `XSRF-TOKEN` qu'axios recopie dans
  l'en-tête `X-XSRF-TOKEN`. Laravel compare les deux : un site tiers ne peut pas lire le cookie, donc ne peut pas forger l'en-tête.
- *Pourquoi le même domaine* : les cookies sont liés à un domaine. Si le front était sur `app.com` et l'API sur `api.autre.com`,
  le navigateur n'enverrait pas le cookie de session.
- *Proxy Vite* : en dev, le navigateur ne parle qu'à `localhost:5173` ; Vite transmet `/api` et `/sanctum` à Laravel.
  `changeOrigin: false` garde l'en-tête `Host`, sinon Laravel générerait des liens vers `127.0.0.1:8000`.
- *`session()->regenerate()`* après connexion : protège contre la fixation de session.
- *`throttle:6,1`* : 6 tentatives par minute (anti force brute).
- *URL signée* (vérification d'e-mail) : Laravel ajoute une signature HMAC calculée avec `APP_KEY` ; modifier l'URL invalide la signature.

**Mot de passe oublié** (ajouté après la V1)
- Fichiers : `PasswordResetController.php`, `ForgotPasswordRequest.php`, `ResetPasswordRequest.php`,
  `app/Notifications/ResetPassword.php`, `User::sendPasswordResetNotification()`, `lang/fr/passwords.php`,
  `tests/Feature/PasswordResetTest.php` ; côté front `pages/ForgotPasswordPage.tsx`, `pages/ResetPasswordPage.tsx` et son test.
- Le *password broker* de Laravel (`Password::sendResetLink`, `Password::reset`) crée un jeton aléatoire, n'en stocke
  que le **haché** dans `password_reset_tokens`, l'invalide après usage et le refuse après 60 minutes.
- Le lien de l'e-mail mène à la page React `/reset-password/{token}?email=...`, qui renvoie jeton, e-mail et nouveau
  mot de passe à l'API.
- *Anti-énumération* : `forgot-password` répond la même chose que l'adresse ait un compte ou non.
- Après le changement, **toutes les sessions** de l'utilisateur sont supprimées (table `sessions`).
- Les jetons sont supprimés avec le compte (pas de clé étrangère, la table est indexée par e-mail) et purgés chaque jour
  par `auth:clear-resets`.

**Questions possibles**
- *Pourquoi répondre pareil pour une adresse inconnue ?* Sinon le formulaire permettrait de vérifier qui a un compte.
- *Pourquoi fermer les autres sessions ?* Si quelqu'un utilisait le compte avec l'ancien mot de passe, il perd l'accès.
- *Pourquoi pas un jeton JWT dans le localStorage ?* Un script injecté (XSS) peut lire le localStorage, pas un cookie
  `HttpOnly`. Et on n'a pas besoin de jetons puisque front et API sont sur le même domaine.
- *Que se passe-t-il si le jeton CSRF expire ?* L'API répond 419 ; l'intercepteur d'axios (`client.ts`) redemande un
  cookie et rejoue la requête une fois.
- *Pourquoi les routes OAuth sont-elles dans `web.php` ?* Voir le commentaire du fichier : au retour de Google, il n'y a
  pas de Referer de notre domaine, donc Sanctum n'activerait pas la session.

---

## Jalon 2 — Modèle de données, enums, organismes

**À lire**
1. Les migrations `backend/database/migrations/2026_10_10_*` (index, clés étrangères, `cascadeOnDelete`).
2. Les enums `backend/app/Enums/*` (surtout `RequestStatus`).
3. Les modèles : `Profile` (cast `encrypted`), `Organization` (scope `search`), `GoogleAccount`, `RequestEvent`.
4. `backend/app/Services/OrganizationResolver.php` et son test `tests/Unit/OrganizationResolverTest.php`.
5. `backend/database/seeders/OrganizationSeeder.php` (règle : aucun contact non vérifié).
6. Front : `features/organizations/*`, `pages/OrganizationsPage.tsx`.

**Notions**
- *Enum PHP « backed »* (`enum X: string`) : valeur stockée en base, cast automatique par Eloquent.
- *Cast `encrypted`* : chiffrement AES avec `APP_KEY`. Conséquence : on ne peut ni chercher ni indexer ces colonnes,
  et **perdre `APP_KEY` rend les données illisibles**.
- *Index composés* : `(user_id, status)` pour le tableau de bord, `(status, due_at)` pour la commande quotidienne.
- *`#[Fillable]`* (attribut PHP de Laravel 13) : protège contre l'assignation de masse. `status`, `created_by` et
  `verified_at` n'y sont volontairement pas.
- *Résolution de domaine* : on teste `fr.linkedin.com` puis `linkedin.com` en une seule requête `WHERE domain IN (...)`.

**Questions possibles**
- *Pourquoi `restrictOnDelete` sur `organization_id` ?* On ne veut pas qu'une suppression d'organisme efface des demandes.
- *Pourquoi le nom `ErasureRequest` ?* `Request` entre en conflit avec `Illuminate\Http\Request`.

---

## Jalon 3 — LegalAdvisor, LetterGenerator, assistant de création

**À lire**
1. `backend/app/Services/LegalAdvisor.php`, `LegalAdvice.php`, et `tests/Unit/LegalAdvisorTest.php`.
2. `backend/app/Services/LetterGenerator.php`, `Letters/LetterData.php`, les vues `resources/views/letters/**`.
3. `tests/Unit/LetterGeneratorTest.php` et les snapshots dans `tests/.pest/snapshots`.
4. `backend/app/Http/Controllers/Api/ErasureRequestController.php` (`store`, `update`).
5. Front : `features/requests/wizard/*` (UrlStep → OrganizationStep → DataStep → NewRequestWizard), `features/requests/DraftEditor.tsx`.
6. `frontend/src/lib/schemas.ts` (zod).

**Notions**
- *Règles déterministes* : même entrée = même sortie. C'est ce qui rend les **tests par snapshot** possibles.
- *Blade en texte brut* : `{!! !!}` au lieu de `{{ }}`, sinon les apostrophes deviendraient `&#039;`. Ce n'est pas
  une faille XSS ici : le texte n'est jamais injecté comme HTML (React l'affiche dans un `<textarea>`).
- *Objet de valeur immuable* (`final readonly class`) : `LetterData`, `LegalAdvice`.
- *Les avertissements ne bloquent jamais* : ils sont calculés à l'affichage et ne sont jamais stockés.

**Questions possibles**
- *Comment testez-vous une lettre ?* Snapshot : le texte de référence est enregistré une fois, puis comparé caractère par caractère.
- *Si on modifie un modèle de lettre ?* Le test échoue ; on relit le nouveau texte puis on met à jour la référence avec `pest --update-snapshots`.

---

## Jalon 4 — Machine à états, historique, délais, commande quotidienne

**À lire**
1. `backend/app/Enums/RequestStatus.php` (`allowedTransitions`).
2. `backend/app/Models/ErasureRequest.php` : `transitionTo()`, le garde-fou `updating`, `availableActions()`, `prunable()`.
3. `backend/app/Models/RequestEvent.php` (historique en ajout seul).
4. `backend/app/Services/DeadlineCalculator.php` et son test.
5. `backend/app/Console/Commands/CheckDeadlinesCommand.php`, `routes/console.php`, `app/Notifications/DeadlinePassed.php`.
6. `backend/app/Http/Controllers/Api/ErasureRequestActionController.php` (`markSent`, `response`, `complaint`, `close`).
7. `tests/Feature/LifecycleTest.php`.

**Notions**
- *Machine à états* : un seul endroit décide si une transition est autorisée ; `transitionTo()` vérifie, modifie et
  journalise **dans une transaction** (tout ou rien).
- *Garde-fou* : l'événement Eloquent `updating` lève une exception si `status` change hors de `transitionTo()`.
- *`addMonthsNoOverflow`* : 31 janvier + 1 mois = 28/29 février (et non 3 mars).
- *`lazyById()`* : parcourt les lignes par paquets, sans tout charger en mémoire.
- *`Prunable` + `model:prune`* : purge automatique (conservation limitée).

**Questions possibles**
- *Que se passe-t-il si la commande tourne deux fois ?* La 2e fois, les demandes sont déjà `overdue` : elles ne
  correspondent plus au scope `pastDue` (testé dans `LifecycleTest`).
- *Pourquoi l'historique n'est-il jamais modifié ?* Il sert de preuve (dossier CNIL).

---

## Jalon 5 — OAuth Gmail, job d'envoi, limite d'envois, relances

**À lire**
1. `backend/app/Http/Controllers/Api/GoogleAccountController.php`.
2. `backend/app/Services/Mail/*` : l'interface `MailSender`, `GmailSender`, `FakeSender`, `GoogleTokenRevoker`.
3. `backend/app/Providers/AppServiceProvider.php` (liaison interface → implémentation).
4. `backend/app/Jobs/SendErasureRequestJob.php`.
5. `ErasureRequestActionController::send()`, `reminder()` et `ensureCanSendWithGmail()`.
6. `tests/Feature/SendingTest.php`.
7. Front : `features/settings/GmailConnection.tsx`, l'attente de l'envoi (polling) dans `features/requests/useRequests.ts` (`useRequest`) et `pages/RequestDetailPage.tsx`.

**Notions**
- *Inversion de dépendance* : le code dépend de l'interface `MailSender` ; le conteneur fournit `GmailSender` ou `FakeSender`.
  En test, on vérifie ce qui a été « envoyé » sans appeler Google.
- *OAuth* : `access_type=offline` donne un **refresh token** (durable) ; on l'échange contre un **access token**
  (valable 1 h) à chaque envoi. `prompt=consent` force Google à renvoyer le refresh token.
- *Scope minimal* : `gmail.send` permet d'envoyer, pas de lire (lecture = scope « restreint », audit de sécurité payant).
- *Job asynchrone* : la requête HTTP répond 202 tout de suite ; le worker envoie, avec 3 essais espacés.
  `ShouldBeUnique` évite deux envois en cas de double clic. `SerializesModels` ne met que l'id en file.
- *RateLimiter* : 10 envois par 24 h et par utilisateur.

**Questions possibles**
- *Et si l'utilisateur révoque l'accès chez Google ?* `invalid_grant` → `GoogleAccountDisconnectedException` →
  événement `send_failed`, compte Google supprimé, pas de nouvel essai.
- *Pourquoi le mode « En production non validée » ?* En mode Test, Google invalide les refresh tokens au bout de 7 jours.

---

## Jalon 6 — Réponses, PDF CNIL, export, suppression du compte

**À lire**
1. `StoreResponseRequest.php` (règle `in` construite depuis `responseOutcomes()`).
2. `ComplaintController.php` et la vue `resources/views/complaints/cnil.blade.php`.
3. `AccountController.php` (`export`, `destroy`) et `tests/Feature/AccountTest.php`.
4. Front : `features/requests/RequestActions.tsx`, `ResponseForm.tsx`, `Timeline.tsx`, `features/settings/*`, `pages/PrivacyPage.tsx`.

**Notions**
- *Suppression en cascade* au niveau SQL (`cascadeOnDelete`) : un seul `$user->delete()` suffit.
- *Révoquer avant de supprimer* : sinon le jeton resterait valable chez Google.
- *Policy* : `ErasureRequestPolicy`, appliquée par `->can('update', 'erasureRequest')` sur les routes (testé route par route dans `ErasureRequestPolicyTest`).

---

## Les notions React du projet

| Notion | Où la voir | En une phrase |
|---|---|---|
| Composant, props | `components/ui/Button.tsx` | Une fonction qui reçoit des paramètres (props) et renvoie du JSX. |
| JSX | partout | Syntaxe proche du HTML dans le JavaScript ; `className` au lieu de `class`, `{expression}` pour insérer une valeur. |
| `useState` | `DashboardPage.tsx` (filtre), `NewRequestWizard.tsx` (étape) | Une valeur propre au composant ; la modifier relance le rendu. |
| État remonté | `NewRequestWizard.tsx` | Le parent garde l'état partagé et le transmet aux étapes par props et callbacks (`onNext`, `onSelect`). |
| `useEffect` | `DraftEditor.tsx` (`reset`), `Layout.tsx` (`scrollTo`) | Code exécuté après l'affichage quand une dépendance change. |
| `useId` | `components/ui/Field.tsx` | Identifiant unique pour relier `<label>` et `<input>`. |
| `useDeferredValue` | `OrganizationSearch.tsx` | Laisse la saisie fluide en retardant la valeur utilisée pour la recherche. |
| Rendu conditionnel | partout (`{cond && <X />}`) | Afficher ou non un bloc selon une condition. |
| Listes et `key` | `RequestList.tsx` | `key` permet à React de savoir quel élément de la liste a changé. |
| Contexte / Providers | `main.tsx` | `QueryClientProvider` et `BrowserRouter` rendent le cache et le routeur disponibles partout. |
| React Router | `App.tsx`, `RouteGuards.tsx` | Routes imbriquées, `<Outlet />`, `<Navigate />`, `useParams`, `useNavigate`. |
| TanStack Query `useQuery` | `useAuth.ts`, `useRequests.ts` | Lit et met en cache une donnée serveur ; fournit `isPending`, `error`, `data`. |
| TanStack Query `useMutation` | `useRequests.ts` | Modifie une donnée serveur, puis met à jour (`setQueryData`) ou invalide (`invalidateQueries`) le cache. |
| Clés de cache | `requestKeys` dans `useRequests.ts` | Hiérarchiques : invalider `['requests']` rafraîchit listes et détails. |
| Polling | `useRequest` (`refetchInterval`) | Relire la demande toutes les 2 s pendant que le worker envoie l'e-mail. |
| react-hook-form + zod | `LoginPage.tsx`, `DataStep.tsx`, `lib/schemas.ts` | Le schéma zod valide et donne le type TypeScript ; `register()` branche les champs. |
| Erreurs serveur → champs | `lib/errors.ts` (`applyServerErrors`) | Les erreurs 422 de Laravel s'affichent sous les bons champs. |

**Pourquoi TanStack Query plutôt que `useEffect` + `fetch` ?** Il gère pour nous le cache partagé, les états de
chargement et d'erreur, les nouvelles tentatives et le rafraîchissement après une modification. Le code serait sinon
répété dans chaque page.

---

## Tests : ce qui est couvert

- **Unitaires (back)** : `DeadlineCalculator` (31 janvier, bissextile, prolongation), `RequestStatus` (transitions
  autorisées et interdites), `LegalAdvisor` (chaque règle), `LetterGenerator` (snapshots), `OrganizationResolver`.
- **Fonctionnels (API)** : authentification et vérification d'e-mail, Policy sur chaque route, création et modification
  de brouillon, envoi via `FakeSender`, e-mail vérifié obligatoire, limite de 10 envois, relances, réponses, plainte et
  PDF, commande des délais, purge à 12 mois, export, suppression du compte en cascade, chiffrement en base, messages en français.
- **Front** : l'assistant (validation de chaque étape, canal Gmail désactivé sans e-mail, création du brouillon), les
  actions de la page de détail selon le statut, le calcul des jours restants.
