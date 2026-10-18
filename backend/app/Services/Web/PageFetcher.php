<?php

namespace App\Services\Web;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

/**
 * Lit une page web pour le compte de l'utilisateur, en respectant :
 *  - la sécurité du serveur (SafeHttpClient : protection SSRF, délais, taille) ;
 *  - la volonté des sites (robots.txt, vérifié à chaque étape de redirection).
 */
class PageFetcher
{
    private const ACCEPT = 'text/html,application/xhtml+xml;q=0.9,text/plain;q=0.8';

    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    public function __construct(
        private readonly SafeHttpClient $http,
        private readonly RobotsPolicy $robots,
        private readonly HtmlTextExtractor $extractor,
        private readonly UrlGuard $guard,
    ) {}

    /**
     * @throws FetchException
     */
    public function fetch(string $url): FetchedPage
    {
        $current = $url;
        $maxRedirects = (int) config('scanner.fetch.max_redirects');

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            // Sécurité d'abord : une URL interne est refusée avant même de demander son robots.txt.
            try {
                $this->guard->check($current);
            } catch (UnsafeUrlException) {
                throw new FetchException(FetchFailure::UnsafeUrl);
            }
            if (! $this->robots->allows($current)) {
                throw new FetchException(FetchFailure::RobotsDisallowed);
            }

            $response = $this->http->get($current, self::ACCEPT);
            $status = $response['status'];

            if (in_array($status, self::REDIRECT_STATUSES, true) && $response['location'] !== null) {
                // Location peut être relative ("/nouvelle-page") : on la résout par rapport à l'URL courante.
                $current = (string) UriResolver::resolve(new Uri($current), new Uri($response['location']));

                continue;
            }

            if ($status === 404 || $status === 410) {
                throw new FetchException(FetchFailure::NotFound, $status);
            }
            if ($status < 200 || $status >= 300) {
                throw new FetchException(FetchFailure::HttpError, $status);
            }

            $type = $response['content_type'];
            if ($type !== '' && ! str_contains($type, 'html') && ! str_contains($type, 'text/plain')) {
                throw new FetchException(FetchFailure::NotHtml, $status);
            }

            $content = str_contains($type, 'text/plain')
                ? ['title' => '', 'text' => trim((string) preg_replace('/\s+/u', ' ', $response['body']))]
                : $this->extractor->extract($response['body']);

            return new FetchedPage($current, $status, $content['title'], $content['text']);
        }

        throw new FetchException(FetchFailure::TooManyRedirects);
    }
}
