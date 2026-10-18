<?php

namespace App\Services;

use App\Enums\CheckOutcome;
use App\Enums\DataType;
use App\Enums\EventType;
use App\Enums\VerificationStatus;
use App\Models\ErasureCheck;
use App\Models\ErasureRequest;
use App\Notifications\ErasureStillPresent;
use App\Services\Identity\Identity;
use App\Services\Identity\IdentityMatcher;
use App\Services\Web\FetchException;
use App\Services\Web\FetchFailure;
use App\Services\Web\PageFetcher;
use Illuminate\Support\Facades\DB;

/**
 * Vérifie qu'un effacement accepté a vraiment eu lieu : on relit la page et on y cherche les données.
 *
 *  - page disparue (404/410) ou données absentes → effacement vérifié (fin) ;
 *  - données présentes → nouvelle vérification dans 7 jours ; après N fois : alerte + plainte possible ;
 *  - page injoignable → nouvel essai ; après N fois : vérification impossible ;
 *  - lecture interdite (robots.txt…) → vérification impossible tout de suite.
 */
class ErasureVerifier
{
    public function __construct(
        private readonly PageFetcher $fetcher,
        private readonly IdentityMatcher $matcher,
    ) {}

    public function verify(ErasureRequest $request, bool $manual = false): ErasureCheck
    {
        [$outcome, $failure, $status, $matched] = $this->inspect($request);

        return DB::transaction(function () use ($request, $manual, $outcome, $failure, $status, $matched) {
            $check = $request->checks()->create([
                'outcome' => $outcome,
                'failure' => $failure,
                'http_status' => $status,
                'matched' => $matched === [] ? null : array_map(fn (DataType $t) => $t->value, $matched),
                'manual' => $manual,
            ]);

            $this->applyOutcome($request, $outcome, $failure);

            return $check;
        });
    }

    /**
     * @return array{0: CheckOutcome, 1: FetchFailure|null, 2: int|null, 3: list<DataType>}
     */
    private function inspect(ErasureRequest $request): array
    {
        try {
            $page = $this->fetcher->fetch($request->url);
        } catch (FetchException $e) {
            $outcome = match (true) {
                $e->failure === FetchFailure::NotFound => CheckOutcome::Gone,
                $e->failure->isPermanent() => CheckOutcome::Blocked,
                default => CheckOutcome::Unreachable,
            };

            return [$outcome, $e->failure, $e->status, []];
        }

        $match = $this->matcher->match(Identity::forUser($request->user), $page->title.' '.$page->text);

        // On ne s'intéresse qu'aux données visées par la demande (sinon : toutes celles retrouvées).
        $requested = $request->data_types->values()->all();
        $stillThere = $requested === []
            ? $match->dataTypes
            : array_values(array_filter($match->dataTypes, fn (DataType $t) => in_array($t, $requested, true)));

        return [$stillThere === [] ? CheckOutcome::Absent : CheckOutcome::Present, null, $page->status, $stillThere];
    }

    private function applyOutcome(ErasureRequest $request, CheckOutcome $outcome, ?FetchFailure $failure): void
    {
        $config = config('scanner.verification');
        $count = $request->check_count + 1;
        $next = now()->addDays((int) $config['interval_days']);

        [$verification, $nextCheck, $event] = match ($outcome) {
            CheckOutcome::Gone, CheckOutcome::Absent => [VerificationStatus::Cleared, null, EventType::ErasureVerified],
            CheckOutcome::Blocked => [VerificationStatus::Unverifiable, null, EventType::VerificationImpossible],
            CheckOutcome::Present => $count >= (int) $config['max_present_checks']
                ? [VerificationStatus::StillPresent, null, EventType::ErasureStillPresent]
                : [VerificationStatus::Pending, $next, null],
            CheckOutcome::Unreachable => $count >= (int) $config['max_unreachable_checks']
                ? [VerificationStatus::Unverifiable, null, EventType::VerificationImpossible]
                : [VerificationStatus::Pending, $next, null],
        };

        // Le statut de la demande ne change pas : forceFill/save ne touche que les colonnes de vérification.
        $previous = $request->verification;
        $request->forceFill([
            'verification' => $verification,
            'next_check_at' => $nextCheck,
            'check_count' => $count,
        ])->save();

        // Événement et alerte seulement quand l'état change vraiment (pas à chaque revérification manuelle).
        if ($event !== null && $previous !== $verification) {
            $request->recordEvent($event, array_filter(['outcome' => $outcome->value, 'failure' => $failure?->value]));
            if ($verification === VerificationStatus::StillPresent) {
                $request->user->notify(new ErasureStillPresent($request));
            }
        }
    }
}
