<?php

namespace App\Services\Web;

use RuntimeException;

/**
 * URL refusée avant tout accès réseau (schéma, port, adresse privée…).
 */
class UnsafeUrlException extends RuntimeException {}
