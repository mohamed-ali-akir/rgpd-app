<?php

namespace App\Actions;

use App\Enums\Channel;
use App\Enums\EventType;
use App\Models\ErasureRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\LegalAdvisor;
use App\Services\LetterGenerator;
use App\Services\Letters\LetterData;
use Illuminate\Support\Facades\DB;

/**
 * Crée un brouillon de demande : fondements suggérés, canal conseillé, lettre générée,
 * événement "created". Seul endroit du code qui crée une demande.
 *
 * Une "action" est une classe qui fait une seule chose métier : le contrôleur et le scanner
 * l'appellent tous les deux, la règle n'est donc écrite qu'une fois.
 */
class CreateDraftRequest
{
    public function __construct(
        private readonly LegalAdvisor $advisor,
        private readonly LetterGenerator $letters,
    ) {}

    public function handle(User $user, Organization $organization, DraftInput $input): ErasureRequest
    {
        $advice = $this->advisor->advise($organization, $input->selfPublished, $input->hasAccount, $input->kind);

        $letter = $this->letters->initial(LetterData::make(
            $user,
            $organization,
            $input->url,
            $input->dataTypes,
            $advice->legalGrounds,
            kind: $input->kind,
            details: $input->details,
        ));

        // Le canal demandé n'est accepté que s'il est possible ; sinon on garde le canal conseillé.
        $channel = $input->channel === Channel::Gmail && $organization->privacy_email === null
            ? $advice->suggestedChannel
            : ($input->channel ?? $advice->suggestedChannel);

        return DB::transaction(function () use ($user, $organization, $input, $advice, $letter, $channel) {
            $request = $user->erasureRequests()->create([
                'organization_id' => $organization->id,
                'kind' => $input->kind,
                'url' => $input->url,
                'data_types' => $input->dataTypes,
                'legal_grounds' => $advice->legalGrounds,
                'details' => $input->details,
                'channel' => $channel,
                'subject' => $letter->subject,
                'body' => $letter->body,
            ]);

            $request->recordEvent(EventType::Created, [
                'kind' => $input->kind->value,
                'self_published' => $input->selfPublished,
                'has_account' => $input->hasAccount,
                ...$input->context,
            ]);

            return $request;
        });
    }
}
