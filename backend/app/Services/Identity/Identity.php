<?php

namespace App\Services\Identity;

use App\Models\User;
use LogicException;

/**
 * Identité recherchée : UNIQUEMENT celle du titulaire du compte (jamais un champ libre),
 * pour que l'outil ne puisse pas servir à espionner quelqu'un d'autre.
 */
final readonly class Identity
{
    /**
     * @param  list<string>  $emails
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public array $emails,
        public ?string $phone = null,
    ) {}

    public static function forUser(User $user): self
    {
        $profile = $user->profile ?? throw new LogicException('Profil requis pour rechercher ses données.');

        $emails = array_values(array_unique(array_filter([
            strtolower($user->email),
            $user->googleAccount !== null ? strtolower($user->googleAccount->email) : null,
        ])));

        return new self($profile->first_name, $profile->last_name, $emails, $profile->phone);
    }

    public function fullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }
}
