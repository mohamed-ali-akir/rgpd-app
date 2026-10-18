<?php

namespace App\Services\Scanner\Sources;

use App\Enums\FindingSource;
use App\Services\Identity\Identity;
use App\Services\Scanner\Candidate;

/**
 * Une source de résultats pour le scanner. Le scanner les appelle une par une et isole leurs pannes :
 * une source qui lève une exception est notée « en échec » dans le rapport, les autres continuent.
 * Ajouter une source = une nouvelle classe + une ligne dans AppServiceProvider.
 */
interface ScanSource
{
    public function key(): FindingSource;

    /**
     * @return list<Candidate>
     *
     * @throws SourceSkippedException si la source n'est pas utilisable (non configurée)
     */
    public function scan(Identity $identity): array;
}
