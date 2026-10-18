<?php

namespace App\Services\Search;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * SearXNG : métamoteur libre et gratuit, hébergé sur notre serveur (Docker), qui interroge
 * DuckDuckGo, Qwant, Mojeek… L'API JSON doit être activée dans sa configuration (formats: json).
 * L'URL est fixée par l'administrateur (SEARXNG_URL) : c'est un service interne de confiance,
 * elle ne passe donc pas par UrlGuard.
 */
class SearxngSearchProvider implements SearchProvider
{
    public function __construct(private readonly ?string $baseUrl) {}

    public function isAvailable(): bool
    {
        return $this->baseUrl !== null && $this->baseUrl !== '';
    }

    public function search(string $query, int $limit): array
    {
        if (! $this->isAvailable()) {
            throw new SearchUnavailableException('SearXNG non configuré.');
        }

        try {
            $response = Http::acceptJson()
                ->timeout((float) config('scanner.search.timeout'))
                // Deux nouveaux essais espacés en cas d'erreur réseau ou serveur, pas en cas d'erreur 4xx.
                ->retry([500, 1500], when: fn ($e) => $e instanceof ConnectionException, throw: false)
                ->get(rtrim((string) $this->baseUrl, '/').'/search', [
                    'q' => $query,
                    'format' => 'json',
                    'language' => 'fr',
                    'safesearch' => 0,
                ]);
        } catch (ConnectionException) {
            throw new SearchUnavailableException('SearXNG injoignable.');
        }

        if ($response->failed()) {
            throw new SearchUnavailableException('SearXNG a répondu HTTP '.$response->status().'.');
        }

        $items = $response->json('results');
        if (! is_array($items)) {
            throw new SearchUnavailableException('Réponse SearXNG illisible.');
        }

        $results = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['url']) || ! is_string($item['url'])) {
                continue;
            }
            $results[] = new SearchResult(
                $item['url'],
                is_string($item['title'] ?? null) ? $item['title'] : '',
                is_string($item['content'] ?? null) ? $item['content'] : '',
            );
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }
}
