<?php

namespace App\Services\Search;

/**
 * Moteur de recherche web. Plusieurs implémentations interchangeables (choisies par SEARCH_PROVIDER) :
 * aucun moteur, moteur simulé (tests, démo) ou SearXNG auto-hébergé. Ajouter un moteur
 * (Brave, Mojeek…) ne demande qu'une nouvelle classe, sans toucher au scanner.
 */
interface SearchProvider
{
    /** false : aucun moteur configuré, la recherche web est simplement sautée. */
    public function isAvailable(): bool;

    /**
     * @return list<SearchResult>
     *
     * @throws SearchUnavailableException
     */
    public function search(string $query, int $limit): array;
}
