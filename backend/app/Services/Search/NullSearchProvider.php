<?php

namespace App\Services\Search;

/**
 * Aucun moteur configuré (valeur par défaut) : le scanner n'utilise que les autres sources.
 */
class NullSearchProvider implements SearchProvider
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function search(string $query, int $limit): array
    {
        return [];
    }
}
