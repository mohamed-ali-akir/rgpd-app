<?php

namespace App\Services\Identity;

use App\Enums\DataType;

final readonly class IdentityMatch
{
    /**
     * @param  list<DataType>  $dataTypes  données de l'utilisateur retrouvées dans le texte
     * @param  int  $confidence  0 à 100 : probabilité que le texte parle bien de l'utilisateur
     */
    public function __construct(
        public array $dataTypes,
        public int $confidence,
    ) {}

    public static function none(): self
    {
        return new self([], 0);
    }

    public function found(): bool
    {
        return $this->dataTypes !== [];
    }

    public function has(DataType $type): bool
    {
        return in_array($type, $this->dataTypes, true);
    }
}
