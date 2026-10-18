<?php

namespace App\Services\Search;

use RuntimeException;

/** Le moteur de recherche n'a pas répondu correctement (panne, limite atteinte, réponse illisible). */
class SearchUnavailableException extends RuntimeException {}
