<?php

namespace App\Services\Scanner;

use App\Enums\DataType;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\User;
use App\Services\OrganizationResolver;
use App\Services\Web\UrlNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Enregistre les résultats d'un scan sans doublon : une page déjà connue est mise à jour
 * (vue à nouveau, données détectées complétées), jamais recréée. La décision de l'utilisateur
 * ("pas moi", "ignorer") n'est jamais remise en question par un nouveau scan.
 */
class FindingRecorder
{
    public function __construct(
        private readonly UrlNormalizer $normalizer,
        private readonly OrganizationResolver $organizations,
    ) {}

    /**
     * @param  list<Candidate>  $candidates
     * @return int nombre de nouveaux résultats
     */
    public function record(User $user, Scan $scan, array $candidates): int
    {
        $new = 0;
        foreach ($this->mergeDuplicates($candidates) as $hash => $candidate) {
            $new += $this->recordOne($user, $scan, $hash, $candidate) ? 1 : 0;
        }

        return $new;
    }

    private function recordOne(User $user, Scan $scan, string $hash, Candidate $candidate): bool
    {
        $finding = $user->findings()->where('url_hash', $hash)->first();

        if ($finding === null) {
            try {
                $this->create($user, $scan, $hash, $candidate);

                return true;
            } catch (UniqueConstraintViolationException) {
                // Course rare (deux scans simultanés) : l'autre a créé la ligne entre-temps, on la met à jour.
                $finding = $user->findings()->where('url_hash', $hash)->firstOrFail();
            }
        }

        $this->refresh($finding, $scan, $candidate);

        return false;
    }

    private function create(User $user, Scan $scan, string $hash, Candidate $candidate): void
    {
        $domain = $this->normalizer->domain($candidate->url) ?? '';

        $finding = new Finding([
            'source' => $candidate->source,
            'url' => $candidate->url,
            'url_hash' => $hash,
            'domain' => $domain,
            'organization_id' => $domain !== '' ? $this->organizations->findByDomain($domain)?->id : null,
            'title' => $candidate->title,
            'snippet' => $candidate->snippet,
            'meta' => $candidate->meta,
            'matched' => $candidate->dataTypes,
            'confidence' => $candidate->confidence,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $finding->user_id = $user->id;
        $finding->scan_id = $scan->id;
        $finding->save();
    }

    private function refresh(Finding $finding, Scan $scan, Candidate $candidate): void
    {
        $matched = collect([...$finding->matched->all(), ...$candidate->dataTypes])
            ->unique(fn (DataType $t) => $t->value)->values()->all();

        $finding->forceFill([
            'scan_id' => $scan->id,
            'last_seen_at' => now(),
            'matched' => $matched,
            'confidence' => max($finding->confidence, $candidate->confidence),
        ]);

        // Texte rafraîchi seulement si l'utilisateur n'a pas encore tranché (il a décidé sur ce qu'il a vu).
        if (! $finding->status->isDecided()) {
            $finding->forceFill(['title' => $candidate->title, 'snippet' => $candidate->snippet, 'meta' => $candidate->meta]);
        }

        $finding->save();
    }

    /**
     * Deux sources peuvent trouver la même page : on garde la plus confiante, avec toutes les données détectées.
     *
     * @param  list<Candidate>  $candidates
     * @return array<string, Candidate>
     */
    private function mergeDuplicates(array $candidates): array
    {
        $byHash = [];
        foreach ($candidates as $candidate) {
            $hash = $this->normalizer->hash($candidate->url);
            $existing = $byHash[$hash] ?? null;
            if ($existing === null) {
                $byHash[$hash] = $candidate;

                continue;
            }

            $best = $candidate->confidence > $existing->confidence ? $candidate : $existing;
            $types = collect([...$existing->dataTypes, ...$candidate->dataTypes])->unique(fn (DataType $t) => $t->value)->values()->all();
            $byHash[$hash] = new Candidate($best->source, $best->url, $best->title, $best->snippet, $types, $best->confidence, $best->meta);
        }

        return $byHash;
    }
}
