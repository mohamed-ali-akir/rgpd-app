<?php

namespace App\Services\Scanner;

use App\Enums\DataType;
use App\Enums\FindingSource;

/**
 * Un résultat proposé par une source, avant enregistrement (voir FindingRecorder).
 */
final readonly class Candidate
{
    /**
     * @param  list<DataType>  $dataTypes  données de l'utilisateur publiées sur la page
     * @param  array<string, mixed>  $meta  détails propres à la source (entreprise, page relue ou non…)
     */
    public function __construct(
        public FindingSource $source,
        public string $url,
        public string $title,
        public string $snippet,
        public array $dataTypes,
        public int $confidence,
        public array $meta = [],
    ) {}
}
