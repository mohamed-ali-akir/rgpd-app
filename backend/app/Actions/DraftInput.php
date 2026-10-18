<?php

namespace App\Actions;

use App\Enums\Channel;
use App\Enums\DataType;
use App\Enums\RequestKind;

/**
 * Données nécessaires pour créer un brouillon, quelle que soit leur origine
 * (assistant de création ou résultat du scanner).
 */
final readonly class DraftInput
{
    /**
     * @param  list<DataType>  $dataTypes
     * @param  array<string, mixed>  $context  informations conservées dans l'événement "created"
     */
    public function __construct(
        public string $url,
        public RequestKind $kind = RequestKind::Erasure,
        public array $dataTypes = [],
        public ?string $details = null,
        public bool $selfPublished = false,
        public bool $hasAccount = false,
        public ?Channel $channel = null,
        public array $context = [],
    ) {}
}
