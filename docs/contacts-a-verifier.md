# Contacts RGPD des organismes — à vérifier à la main

> **Rien dans ce document n'est vérifié par un humain.** Il sert de point de départ : pour chaque organisme, ouvrir
> la source, confirmer le contact sur la page officielle, puis seulement le reporter dans
> `backend/database/seeders/OrganizationSeeder.php` avec la date de vérification (`'verified' => 'AAAA-MM-JJ'`).
>
> Recherche faite le 11 octobre 2026. Deux niveaux de confiance :
> - **Lu sur la page officielle** : le contact a été lu sur la page source indiquée (lecture automatisée, donc à confirmer à l'œil).
> - **Piste** : trouvé dans un résultat de recherche ou sur un autre site du même groupe, **pas** lu sur la page officielle du service.

## Lus sur la page officielle

| Organisme | Contact trouvé | Source | Remarques |
|---|---|---|---|
| PagesJaunes (Solocal) | E-mail : `dpo@solocal.com` · Formulaire particuliers : https://www.pagesjaunes.fr/plusdeservices/nouscontacter/mescoordonnees · Courrier : Solocal Group, DPO, 204 rond-point du Pont de Sèvres, 92649 Boulogne-Billancourt Cedex | https://www.pagesjaunes.fr/infoslegales/vieprivee-protection/ (version du 30/09/2026) | L'adresse e-mail est masquée sur la page (protection anti-robots) : la vérifier dans un navigateur. Il existe aussi un formulaire pour les professionnels (`…/nouscontacter/supprimermesinformations`). |
| Societe.com | E-mail : `dpo@societe.com` | https://www.societe.com/pages/politique-de-protection-des-donnees-personnelles.html | Registre légal : refus probable (art. 17.3.b). La page renvoie aussi vers un formulaire de contact. |
| Pappers | Formulaire : https://www.pappers.fr/reclamation | https://www.pappers.fr/politique-de-protection-des-donnees-personnelles | Une adresse e-mail est citée mais masquée sur la page : la relever dans un navigateur. Registre légal. |
| Qwant | E-mail : `dpo@qwant.com` · Courrier : Délégué à la Protection des Données, 42 avenue de la Porte de Clichy, 75017 Paris | https://about.qwant.com/legal/confidentialite/ (mise à jour du 16/09/2026) | Moteur de recherche : pour un déréférencement, le formulaire dédié apparaît dans les résultats de recherche à l'adresse https://report.qwant.com/removal/fr (piste, non ouvert). |
| Google (recherche) | Formulaire de déréférencement (droit à l'oubli) : https://reportcontent.google.com/forms/rtbf | https://support.google.com/legal/answer/10769224?hl=fr | Vaut pour `google.com` et `google.fr`. À renseigner dans `privacy_form_url`. |
| Bing (Microsoft) | Formulaire de blocage de résultats en Europe : https://www.bing.com/webmaster/tools/eu-privacy-request | La page elle-même (titre « Bing - Formulaire de demande de blocage de résultats de recherche en Europe ») | À renseigner dans `privacy_form_url`. |

## Pistes (non lues sur la page officielle du service)

| Organisme | Piste | Où elle a été trouvée | À faire |
|---|---|---|---|
| Leboncoin | `fr.privacy+leboncoin@adevinta.com` | Politique de **Leboncoin Hôtel** (hotel.leboncoin.fr), autre service du groupe | La page principale https://www.leboncoin.fr/dc/cookies bloque la lecture automatique : y chercher la section « Délégué à la protection des données ». |
| Doctrine | `dpo@doctrine.fr` | Interview de leur DPO sur blog.doctrine.fr | Confirmer sur https://www.doctrine.fr/donnees_personnelles/text (section « exercer vos droits ») ; la page décrit aussi une procédure de pseudonymisation des décisions. |
| Manageo | `data-gouvernance@manageo.fr` | Résumé d'un PDF de politique (manageo.fr/contents/…manageo.pdf) | Ouvrir le PDF et confirmer. La FAQ indique qu'un entrepreneur individuel peut demander à sortir de la diffusion publique. |
| 118 712 (Orange) | Page « Contactez-nous » du site | Résultat de recherche sur la politique de l'annuaire | Le lien de la politique trouvé redirige vers une page 404 : chercher la politique actuelle sur 118712.fr. La liste rouge auprès de son opérateur est aussi une solution. |
| Verif.com | Centre de support (pas d'e-mail trouvé) ; responsable de traitement indiqué : Dun & Bradstreet Belgium N.V. (Altares – D&B) | Résultats de recherche (verif.com/en/data_privacy) | La page bloque la lecture automatique. |
| Vinted | Rien de fiable : seulement des adresses d'autres services du groupe (Vinted Go, Vinted Ventures, recrutement) | Résultats de recherche | Lire la fin de https://www.vinted.com/privacy-policy (section contact). Ne pas utiliser les adresses des autres services. |
| LinkedIn | Lien « contacter notre DPO » dans la politique de confidentialité | https://www.linkedin.com/legal/privacy-policy (résultat de recherche) | Relever l'URL exacte du formulaire DPO. |
| Facebook / Instagram (Meta) | Coordonnées du DPO de Meta Platforms Ireland dans la politique de confidentialité | https://www.facebook.com/privacy/policy (résultat de recherche) | Relever l'URL du formulaire. |
| Infogreffe | Aucune politique principale trouvée (seulement celles de sites annexes) | — | Chercher la politique de confidentialité d'infogreffe.fr. Registre légal. |

## Non trouvés

Sites qui refusent la lecture automatique, ou pas encore cherchés : **Le Monde, Le Figaro, Copains d'avant**
(lecture refusée) ; **Le Parisien, Ouest-France, 20 Minutes, Jeuxvideo.com, Doctissimo, BODACC, Annuaire des
entreprises, X (Twitter), TikTok, YouTube, Pinterest, Reddit, 118 712**.

Méthode conseillée : sur chaque site, ouvrir le lien « Données personnelles », « Politique de confidentialité » ou
« Mentions légales » en bas de page, chercher « DPO », « délégué » ou « exercer vos droits », puis noter l'adresse
exacte de la page et la date de mise à jour.
