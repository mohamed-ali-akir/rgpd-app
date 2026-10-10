<?php

namespace App\Enums;

enum Channel: string
{
    /** L'app envoie la lettre depuis le Gmail de l'utilisateur. */
    case Gmail = 'gmail';

    /** L'utilisateur l'envoie lui-même (formulaire du site, courrier) puis confirme l'envoi. */
    case Manual = 'manual';
}
