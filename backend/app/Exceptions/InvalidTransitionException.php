<?php

namespace App\Exceptions;

use App\Enums\RequestStatus;
use RuntimeException;

class InvalidTransitionException extends RuntimeException
{
    public static function between(RequestStatus $from, RequestStatus $to): self
    {
        return new self(sprintf(
            'Impossible de passer une demande du statut « %s » au statut « %s ».',
            $from->label(),
            $to->label(),
        ));
    }
}
