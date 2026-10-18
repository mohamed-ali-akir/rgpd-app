<?php

namespace App\Enums;

/**
 * Droit exercé par une demande. La V1 ne gérait que l'effacement : le modèle s'appelle
 * toujours ErasureRequest pour ne pas casser l'existant, mais il couvre désormais ces quatre droits.
 */
enum RequestKind: string
{
    case Erasure = 'erasure';             // art. 17 (+ opposition art. 21)
    case Access = 'access';               // art. 15
    case Rectification = 'rectification'; // art. 16
    case Restriction = 'restriction';     // art. 18

    public function label(): string
    {
        return match ($this) {
            self::Erasure => 'Effacement',
            self::Access => 'Accès',
            self::Rectification => 'Rectification',
            self::Restriction => 'Limitation du traitement',
        };
    }

    public function article(): string
    {
        return match ($this) {
            self::Erasure => 'art. 17',
            self::Access => 'art. 15',
            self::Rectification => 'art. 16',
            self::Restriction => 'art. 18',
        };
    }

    /** « une demande d'effacement de données personnelles » (utilisé dans la relance). */
    public function requestNoun(): string
    {
        return match ($this) {
            self::Erasure => 'une demande d\'effacement de données personnelles',
            self::Access => 'une demande d\'accès aux données personnelles',
            self::Rectification => 'une demande de rectification de données personnelles',
            self::Restriction => 'une demande de limitation du traitement de données personnelles',
        };
    }

    /** Ce qu'on redemande dans la relance. */
    public function reminderAction(): string
    {
        return match ($this) {
            self::Erasure => 'procéder à l\'effacement de ces données',
            self::Access => 'me communiquer une copie de ces données et les informations demandées',
            self::Rectification => 'procéder à la rectification de ces données',
            self::Restriction => 'limiter le traitement de ces données',
        };
    }

    /** Le champ « details » est obligatoire pour ces droits (correction ou motif). */
    public function requiresDetails(): bool
    {
        return $this === self::Rectification || $this === self::Restriction;
    }

    /** Une demande d'accès porte sur toutes les données : cocher des types n'est pas obligatoire. */
    public function requiresDataTypes(): bool
    {
        return $this !== self::Access;
    }
}
