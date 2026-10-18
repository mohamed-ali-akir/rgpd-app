<?php

namespace App\Services\Search;

/**
 * Moteur simulé : renvoie des résultats fixés à l'avance (tests, mode démo). Aucun accès réseau.
 */
class FakeSearchProvider implements SearchProvider
{
    /** @var list<string> requêtes reçues (vérifiées par les tests) */
    public array $queries = [];

    public bool $failing = false;

    /**
     * @param  array<string, list<SearchResult>>  $results  résultats par requête exacte ; "*" = toute requête
     */
    public function __construct(public array $results = []) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function search(string $query, int $limit): array
    {
        $this->queries[] = $query;
        if ($this->failing) {
            throw new SearchUnavailableException('Moteur simulé en panne.');
        }

        return array_slice($this->results[$query] ?? $this->results['*'] ?? [], 0, $limit);
    }
}
