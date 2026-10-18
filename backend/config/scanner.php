<?php

/*
 * Réglages de tout ce qui lit le web côté serveur : vérification de l'effacement et scanner.
 * Les valeurs par défaut sont prudentes ; chacune peut être ajustée par variable d'environnement.
 */
return [

    // Lecture de pages web (PageFetcher).
    'fetch' => [
        // Identifiable par les administrateurs de sites, avec un lien vers notre politique.
        'user_agent' => env('SCANNER_USER_AGENT', 'EffaceMoiBot/2.0 (+'.env('APP_URL', 'http://localhost').'/privacy)'),
        // Jeton cherché dans robots.txt (User-agent: EffaceMoiBot).
        'robots_token' => 'EffaceMoiBot',
        'connect_timeout' => (float) env('SCANNER_CONNECT_TIMEOUT', 5),
        'timeout' => (float) env('SCANNER_TIMEOUT', 10),
        // Au-delà, la page n'est pas lue (protège la mémoire du serveur).
        'max_bytes' => (int) env('SCANNER_MAX_BYTES', 2_000_000),
        'max_redirects' => 3,
        // robots.txt mis en cache : on ne le redemande pas à chaque page d'un même site.
        'robots_cache_seconds' => 86400,
        'robots_error_cache_seconds' => 3600,
    ],

    // Moteur de recherche web utilisé par le scanner : "none", "fake" ou "searxng".
    'search' => [
        'provider' => env('SEARCH_PROVIDER', 'none'),
        'searxng_url' => env('SEARXNG_URL'),
        'timeout' => (float) env('SEARCH_TIMEOUT', 15),
        'results_per_query' => 10,
    ],

    // Scanner.
    'scan' => [
        // Scans lancés à la main par utilisateur sur 30 jours glissants.
        'monthly_limit' => (int) env('SCANNER_MONTHLY_LIMIT', 4),
        // Nombre maximal de pages relues pour confirmer un résultat de recherche.
        'max_pages_fetched' => (int) env('SCANNER_MAX_PAGES', 15),
        // Un résultat seulement vu dans l'extrait (page non relue) perd une partie de sa confiance.
        'unverified_confidence_factor' => 0.7,
        // Résultats sous ce seuil ignorés (bruit).
        'min_confidence' => 30,
        // Scan automatique (sur option) : intervalle minimal entre deux scans.
        'auto_interval_days' => 30,
        // Purge : résultats rejetés ou ignorés, et scans anciens.
        'dismissed_findings_retention_months' => 6,
        'scans_retention_months' => 12,
    ],

    // Vérification de l'effacement après une réponse positive.
    'verification' => [
        'first_delay_days' => 3,
        'interval_days' => 7,
        // Données toujours en ligne après ce nombre de vérifications : on alerte l'utilisateur.
        'max_present_checks' => 4,
        // Page injoignable autant de fois : vérification impossible.
        'max_unreachable_checks' => 3,
        // Vérification manuelle : au plus une par heure et par demande.
        'manual_cooldown_seconds' => 3600,
    ],

    // API Recherche d'entreprises (data.gouv.fr) : gratuite, sans clé.
    'company_registry' => [
        'url' => env('COMPANY_REGISTRY_URL', 'https://recherche-entreprises.api.gouv.fr/search'),
        'annuaire_url' => 'https://annuaire-entreprises.data.gouv.fr/entreprise/',
        'timeout' => 10.0,
        'per_page' => 25,
    ],

];
