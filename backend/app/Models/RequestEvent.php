<?php

namespace App\Models;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Une ligne de l'historique d'une demande. Ajout seul : jamais modifiée.
 *
 * @property int $id
 * @property int $erasure_request_id
 * @property EventType $type
 * @property array<string, mixed>|null $payload
 * @property Carbon $occurred_at
 */
#[Fillable(['type', 'payload', 'occurred_at'])]
class RequestEvent extends Model
{
    // Pas de created_at/updated_at : la date est occurred_at.
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RequestEvent $event) {
            $event->occurred_at ??= now();
        });

        // L'historique sert de preuve (ex. dossier CNIL) : on interdit toute modification.
        static::updating(function () {
            throw new LogicException('Un événement de l\'historique ne peut pas être modifié.');
        });
    }

    /** @return BelongsTo<ErasureRequest, $this> */
    public function erasureRequest(): BelongsTo
    {
        return $this->belongsTo(ErasureRequest::class);
    }
}
