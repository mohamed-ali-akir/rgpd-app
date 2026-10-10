<?php

namespace App\Services\Letters;

use App\Enums\DataType;
use App\Enums\LegalGround;
use App\Models\ErasureRequest;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Tout ce qu'il faut pour écrire une lettre, et rien d'autre.
 * LetterGenerator ne lit jamais la base : même LetterData = même lettre.
 */
final readonly class LetterData
{
    /**
     * @param  list<DataType>  $dataTypes
     * @param  list<LegalGround>  $legalGrounds
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public ?string $postalAddress,
        public ?string $phone,
        public string $organizationName,
        public ?string $dpoName,
        public string $url,
        public array $dataTypes,
        public array $legalGrounds,
        public CarbonImmutable $date,
    ) {}

    /**
     * @param  list<DataType>  $dataTypes
     * @param  list<LegalGround>  $legalGrounds
     */
    public static function make(User $user, Organization $organization, string $url, array $dataTypes, array $legalGrounds, ?CarbonImmutable $date = null): self
    {
        $profile = $user->profile;

        return new self(
            firstName: $profile->first_name,
            lastName: $profile->last_name,
            // Adresse d'envoi : celle de Gmail si connecté, sinon celle du compte.
            email: $user->googleAccount?->email ?? $user->email,
            postalAddress: $profile->postal_address,
            phone: $profile->phone,
            organizationName: $organization->name,
            dpoName: $organization->dpo_name,
            url: $url,
            dataTypes: $dataTypes,
            legalGrounds: $legalGrounds,
            date: $date ?? CarbonImmutable::now(),
        );
    }

    public static function fromRequest(ErasureRequest $request, ?CarbonImmutable $date = null): self
    {
        return self::make(
            $request->user,
            $request->organization,
            $request->url,
            $request->data_types->values()->all(),
            $request->legal_grounds->values()->all(),
            $date,
        );
    }

    public function fullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    public function hasObjection(): bool
    {
        return in_array(LegalGround::Objection, $this->legalGrounds, true);
    }
}
