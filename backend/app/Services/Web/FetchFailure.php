<?php

namespace App\Services\Web;

/**
 * Pourquoi une page n'a pas pu être lue. Chaque cause appelle une réaction différente.
 */
enum FetchFailure: string
{
    case UnsafeUrl = 'unsafe_url';               // refusée par UrlGuard
    case RobotsDisallowed = 'robots_disallowed'; // le site interdit les robots sur cette page
    case NotFound = 'not_found';                 // 404 / 410 : la page n'existe plus
    case HttpError = 'http_error';               // autre erreur HTTP (403, 429, 500…)
    case Unreachable = 'unreachable';            // délai dépassé, DNS, connexion refusée…
    case TooLarge = 'too_large';
    case NotHtml = 'not_html';
    case TooManyRedirects = 'too_many_redirects';

    /**
     * Échec qui ne changera pas en réessayant plus tard : inutile d'insister.
     */
    public function isPermanent(): bool
    {
        return match ($this) {
            self::UnsafeUrl, self::RobotsDisallowed, self::TooLarge, self::NotHtml, self::TooManyRedirects => true,
            self::NotFound, self::HttpError, self::Unreachable => false,
        };
    }
}
