<?php

namespace App\Services\Web;

use RuntimeException;

class FetchException extends RuntimeException
{
    public function __construct(
        public readonly FetchFailure $failure,
        public readonly ?int $status = null,
    ) {
        // Message sans l'URL : il peut finir dans les journaux, qui ne doivent contenir aucune donnée personnelle.
        parent::__construct('Lecture impossible : '.$failure->value.($status !== null ? " (HTTP {$status})" : ''));
    }
}
