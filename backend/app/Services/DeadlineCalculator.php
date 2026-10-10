<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Délais de réponse de l'article 12.3 du RGPD :
 * 1 mois, prolongeable de 2 mois (soit 3 mois au total).
 *
 * "NoOverflow" : le 31 janvier + 1 mois = le 28 (ou 29) février, et non le 3 mars.
 */
class DeadlineCalculator
{
    public const INITIAL_MONTHS = 1;

    public const EXTENDED_MONTHS = 3;

    public function initialDueAt(DateTimeInterface $sentAt): CarbonImmutable
    {
        return CarbonImmutable::instance($sentAt)->addMonthsNoOverflow(self::INITIAL_MONTHS);
    }

    public function extendedDueAt(DateTimeInterface $sentAt): CarbonImmutable
    {
        return CarbonImmutable::instance($sentAt)->addMonthsNoOverflow(self::EXTENDED_MONTHS);
    }

    public function isOverdue(DateTimeInterface $dueAt, ?DateTimeInterface $now = null): bool
    {
        return CarbonImmutable::instance($now ?? now())->greaterThan($dueAt);
    }
}
