<?php

use App\Services\DeadlineCalculator;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->calculator = new DeadlineCalculator;
});

it('ajoute un mois au délai initial', function () {
    $due = $this->calculator->initialDueAt(CarbonImmutable::parse('2026-03-10 14:00'));

    expect($due->toDateTimeString())->toBe('2026-04-10 14:00:00');
});

it('ne déborde pas sur le mois suivant le 31 janvier', function () {
    // Sans "NoOverflow", Carbon donnerait le 3 mars.
    $due = $this->calculator->initialDueAt(CarbonImmutable::parse('2027-01-31'));

    expect($due->toDateString())->toBe('2027-02-28');
});

it('gère les années bissextiles', function () {
    $due = $this->calculator->initialDueAt(CarbonImmutable::parse('2028-01-31'));

    expect($due->toDateString())->toBe('2028-02-29');
});

it('calcule la prolongation à 3 mois après l\'envoi', function () {
    expect($this->calculator->extendedDueAt(CarbonImmutable::parse('2026-11-30'))->toDateString())
        ->toBe('2027-02-28');
});

it('gère la fin de mois avec la prolongation', function () {
    expect($this->calculator->extendedDueAt(CarbonImmutable::parse('2026-05-31'))->toDateString())
        ->toBe('2026-08-31');
});

it('détecte un délai dépassé', function () {
    $due = CarbonImmutable::parse('2026-04-10 14:00');

    expect($this->calculator->isOverdue($due, CarbonImmutable::parse('2026-04-10 13:59')))->toBeFalse()
        ->and($this->calculator->isOverdue($due, CarbonImmutable::parse('2026-04-10 14:01')))->toBeTrue();
});
