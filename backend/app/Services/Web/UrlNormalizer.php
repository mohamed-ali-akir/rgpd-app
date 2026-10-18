<?php

namespace App\Services\Web;

/**
 * Met une URL sous une forme canonique, pour reconnaître deux fois la même page :
 * "HTTPS://Exemple.fr:443/page/?utm_source=x#haut" → "https://exemple.fr/page".
 */
class UrlNormalizer
{
    /** Paramètres de suivi publicitaire, sans effet sur le contenu de la page. */
    private const TRACKING_PARAMS = ['fbclid', 'gclid', 'msclkid', 'mc_cid', 'mc_eid', 'igshid', 'ref', 'ref_src'];

    public function normalize(string $url): string
    {
        $parts = parse_url(trim($url));
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return trim($url);
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower(rtrim($parts['host'], '.'));
        $defaultPort = $scheme === 'https' ? 443 : 80;
        $port = isset($parts['port']) && $parts['port'] !== $defaultPort ? ':'.$parts['port'] : '';

        $path = $parts['path'] ?? '';
        $path = $path === '/' ? '' : rtrim($path, '/');

        $query = '';
        if (isset($parts['query'])) {
            parse_str($parts['query'], $params);
            $params = array_filter(
                $params,
                fn ($key) => ! str_starts_with((string) $key, 'utm_') && ! in_array((string) $key, self::TRACKING_PARAMS, true),
                ARRAY_FILTER_USE_KEY,
            );
            ksort($params);
            $query = $params === [] ? '' : '?'.http_build_query($params);
        }

        return "{$scheme}://{$host}{$port}{$path}{$query}";
    }

    /** Empreinte stable utilisée comme clé unique en base (une URL peut dépasser la taille d'un index). */
    public function hash(string $url): string
    {
        return hash('sha256', $this->normalize($url));
    }

    /** "https://www.Exemple.fr/page" → "exemple.fr" */
    public function domain(string $url): ?string
    {
        $host = parse_url(trim($url), PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }
        $host = strtolower(rtrim($host, '.'));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
