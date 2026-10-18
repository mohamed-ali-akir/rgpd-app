<?php

namespace App\Enums;

/**
 * Où en est la vérification d'un effacement accepté par l'organisme.
 */
enum VerificationStatus: string
{
    case Pending = 'pending';             // vérifications en cours
    case Cleared = 'cleared';             // page disparue ou données absentes
    case StillPresent = 'still_present';  // données toujours en ligne après plusieurs vérifications
    case Unverifiable = 'unverifiable';   // page illisible (robots.txt, erreurs répétées…)

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
