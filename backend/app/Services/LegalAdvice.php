<?php

namespace App\Services;

use App\Enums\Channel;
use App\Enums\LegalGround;

/**
 * Résultat de LegalAdvisor : objet immuable (readonly), facile à tester.
 */
final readonly class LegalAdvice
{
    /**
     * @param  list<LegalGround>  $legalGrounds
     * @param  list<array{code: string, message: string}>  $warnings
     */
    public function __construct(
        public array $legalGrounds,
        public array $warnings,
        public Channel $suggestedChannel,
    ) {}
}
