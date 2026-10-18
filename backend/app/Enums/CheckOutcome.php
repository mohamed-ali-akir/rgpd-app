<?php

namespace App\Enums;

enum CheckOutcome: string
{
    case Gone = 'gone';               // la page n'existe plus (404/410)
    case Absent = 'absent';           // la page existe mais ne contient plus les données
    case Present = 'present';         // les données sont toujours en ligne
    case Unreachable = 'unreachable'; // problème temporaire (délai, erreur serveur…)
    case Blocked = 'blocked';         // lecture impossible durablement (robots.txt, format…)
}
