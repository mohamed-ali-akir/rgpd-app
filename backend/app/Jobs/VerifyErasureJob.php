<?php

namespace App\Jobs;

use App\Enums\VerificationStatus;
use App\Models\ErasureRequest;
use App\Services\ErasureVerifier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Une vérification d'effacement, exécutée par le worker (lecture d'une page web = lent et incertain).
 * Pas de nouvel essai automatique : ErasureVerifier programme lui-même la prochaine vérification.
 */
class VerifyErasureJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(
        public ErasureRequest $erasureRequest,
        public bool $manual = false,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->erasureRequest->id;
    }

    public function handle(ErasureVerifier $verifier): void
    {
        $request = $this->erasureRequest->fresh(['user.profile', 'user.googleAccount']);

        // Demande supprimée entre-temps, ou vérification terminée (sauf revérification demandée à la main).
        if ($request === null || $request->verification === null) {
            return;
        }
        if (! $this->manual && $request->verification !== VerificationStatus::Pending) {
            return;
        }

        $verifier->verify($request, $this->manual);
    }
}
