<?php

namespace App\Actions;

use App\Enums\FindingStatus;
use App\Enums\RequestKind;
use App\Enums\RequestStatus;
use App\Models\ErasureRequest;
use App\Models\Finding;
use App\Models\User;
use App\Services\OrganizationResolver;
use App\Services\Web\UrlNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Transforme des résultats confirmés en brouillons de demandes d'effacement (une par page).
 *
 * Idempotent : rejouer l'action (double clic, nouvel essai réseau) ne crée jamais deux demandes
 * pour la même page. Chaque résultat est traité dans sa propre transaction, avec verrou de ligne :
 * l'échec de l'un n'empêche pas les autres.
 */
class CreateRequestsFromFindings
{
    public function __construct(
        private readonly CreateDraftRequest $createDraft,
        private readonly OrganizationResolver $organizations,
        private readonly UrlNormalizer $normalizer,
    ) {}

    /**
     * @param  list<int>  $findingIds
     * @return array{created: list<array{finding_id: int, request_id: int}>, skipped: list<array{finding_id: int, reason: string, request_id?: int}>}
     */
    public function handle(User $user, array $findingIds): array
    {
        $created = [];
        $skipped = [];
        $openRequests = $this->openRequestsByUrl($user);

        // whereIn sur les résultats de l'utilisateur : impossible de traiter ceux d'un autre.
        $ids = $user->findings()->whereIn('id', $findingIds)->pluck('id');
        foreach ($findingIds as $id) {
            if (! $ids->contains($id)) {
                $skipped[] = ['finding_id' => $id, 'reason' => 'not_found'];
            }
        }

        // Traitement dans l'ordre donné par l'utilisateur : réponse déterministe.
        foreach (array_values(array_filter($findingIds, fn (int $id) => $ids->contains($id))) as $id) {
            $outcome = DB::transaction(function () use ($id, $user, &$openRequests) {
                $finding = Finding::query()->lockForUpdate()->findOrFail($id);

                if ($finding->status !== FindingStatus::Confirmed) {
                    return ['reason' => 'not_confirmed'];
                }
                if ($finding->erasure_request_id !== null) {
                    return ['reason' => 'already_created', 'request_id' => $finding->erasure_request_id];
                }

                // Une demande ouverte existe déjà pour cette page (créée à la main) : on relie, sans doublon.
                $existing = $openRequests[$finding->url_hash] ?? null;
                if ($existing !== null) {
                    $finding->forceFill(['erasure_request_id' => $existing])->save();

                    return ['reason' => 'duplicate_request', 'request_id' => $existing];
                }

                // L'organisme a pu être ajouté depuis le scan : on retente la reconnaissance du domaine.
                $organization = $finding->organization ?? $this->organizations->findByDomain($finding->domain);
                if ($organization === null) {
                    return ['reason' => 'unknown_organization'];
                }

                $request = $this->createDraft->handle($user, $organization, new DraftInput(
                    url: $finding->url,
                    kind: RequestKind::Erasure,
                    dataTypes: $finding->matched->values()->all(),
                    context: [
                        'own_data_confirmed' => true, // l'utilisateur a confirmé "c'est moi"
                        'finding_id' => $finding->id,
                        'source' => $finding->source->value,
                    ],
                ));

                $finding->forceFill(['organization_id' => $organization->id, 'erasure_request_id' => $request->id])->save();
                $openRequests[$finding->url_hash] = $request->id;

                return ['request_id' => $request->id];
            });

            if (isset($outcome['reason'])) {
                $skipped[] = ['finding_id' => $id, ...$outcome];
            } else {
                $created[] = ['finding_id' => $id, 'request_id' => $outcome['request_id']];
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Demandes non clôturées de l'utilisateur, indexées par empreinte d'URL.
     *
     * @return array<string, int>
     */
    private function openRequestsByUrl(User $user): array
    {
        $map = [];
        $user->erasureRequests()
            ->where('status', '!=', RequestStatus::Closed)
            ->get(['id', 'url'])
            ->each(function (ErasureRequest $request) use (&$map) {
                $map[$this->normalizer->hash($request->url)] ??= $request->id;
            });

        return $map;
    }
}
