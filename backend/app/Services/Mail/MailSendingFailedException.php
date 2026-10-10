<?php

namespace App\Services\Mail;

use RuntimeException;

/**
 * Erreur d'envoi temporaire ou inattendue. Le message ne contient jamais de donnée
 * personnelle (pas d'adresse, pas de contenu), car il peut finir dans les logs.
 */
class MailSendingFailedException extends RuntimeException {}
