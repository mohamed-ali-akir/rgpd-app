<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\DataType;
use App\Enums\LegalGround;
use App\Enums\RequestStatus;
use App\Models\ErasureRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ErasureRequest>
 */
class ErasureRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'url' => 'https://exemple.test/profil/'.fake()->numberBetween(1, 9999),
            'data_types' => [DataType::Name, DataType::Email],
            'legal_grounds' => [LegalGround::Objection],
            'channel' => Channel::Gmail,
            'subject' => 'Demande d\'effacement',
            'body' => 'Texte de la lettre',
        ];
    }

    public function manual(): static
    {
        return $this->state(['channel' => Channel::Manual]);
    }

    /**
     * Place directement la demande dans un statut (raccourci réservé aux tests :
     * forceFill sur une demande pas encore enregistrée, donc le garde-fou "updating" ne s'applique pas).
     */
    public function status(RequestStatus $status, ?string $sentAt = '-10 days', ?string $dueAt = '+20 days'): static
    {
        return $this->afterMaking(function (ErasureRequest $request) use ($status, $sentAt, $dueAt) {
            $request->forceFill([
                'status' => $status,
                'sent_at' => $status === RequestStatus::Draft ? null : now()->modify($sentAt),
                'due_at' => $status === RequestStatus::Draft ? null : now()->modify($dueAt),
            ]);
        });
    }
}
