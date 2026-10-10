<?php

namespace App\Enums;

enum DataType: string
{
    case Name = 'name';
    case Email = 'email';
    case Phone = 'phone';
    case PostalAddress = 'postal_address';
    case Photo = 'photo';
    case BirthDate = 'birth_date';
    case Employer = 'employer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Name => 'mes nom et prénom',
            self::Email => 'mon adresse e-mail',
            self::Phone => 'mon numéro de téléphone',
            self::PostalAddress => 'mon adresse postale',
            self::Photo => 'ma photographie',
            self::BirthDate => 'ma date de naissance',
            self::Employer => 'mon employeur',
            self::Other => 'd\'autres informations me concernant',
        };
    }
}
