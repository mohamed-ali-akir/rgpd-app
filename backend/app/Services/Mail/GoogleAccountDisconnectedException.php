<?php

namespace App\Services\Mail;

use RuntimeException;

/**
 * Pas de compte Gmail connecté, ou Google a refusé le refresh token
 * (accès révoqué par l'utilisateur, mot de passe changé…). Il faut reconnecter Gmail.
 */
class GoogleAccountDisconnectedException extends RuntimeException {}
