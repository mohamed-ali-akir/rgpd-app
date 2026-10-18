<?php

namespace App\Enums;

enum ScanTrigger: string
{
    case Manual = 'manual';       // lancé par l'utilisateur (compte dans la limite mensuelle)
    case Scheduled = 'scheduled'; // scan mensuel automatique (option du profil)
}
