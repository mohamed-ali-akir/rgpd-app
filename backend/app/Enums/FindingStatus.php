<?php

namespace App\Enums;

/**
 * Décision de l'utilisateur sur un résultat du scanner (homonymes : rien n'est jamais automatique).
 */
enum FindingStatus: string
{
    case New = 'new';             // pas encore examiné
    case Confirmed = 'confirmed'; // « c'est moi » : peut devenir une demande
    case Rejected = 'rejected';   // « ce n'est pas moi »
    case Ignored = 'ignored';     // c'est moi, mais je ne veux rien demander

    /** Décision prise : un nouveau scan ne remet pas le résultat en question. */
    public function isDecided(): bool
    {
        return $this !== self::New;
    }
}
