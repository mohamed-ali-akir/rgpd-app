<?php

namespace App\Services\Scanner\Sources;

use RuntimeException;

/** La source n'a pas été utilisée (par exemple : aucun moteur de recherche configuré). Ce n'est pas une panne. */
class SourceSkippedException extends RuntimeException {}
