<?php

namespace App\Models;

use App\Enums\CheckOutcome;
use App\Services\Web\FetchFailure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Une vérification de la page après un effacement accepté. Ajout seul : jamais modifiée.
 *
 * @property int $id
 * @property int $erasure_request_id
 * @property CheckOutcome $outcome
 * @property FetchFailure|null $failure
 * @property int|null $http_status
 * @property list<string>|null $matched
 * @property bool $manual
 * @property Carbon $checked_at
 */
#[Fillable(['outcome', 'failure', 'http_status', 'matched', 'manual', 'checked_at'])]
class ErasureCheck extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'outcome' => CheckOutcome::class,
            'failure' => FetchFailure::class,
            'matched' => 'array',
            'manual' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ErasureCheck $check) {
            $check->checked_at ??= now();
        });
        static::updating(function () {
            throw new LogicException('Une vérification enregistrée ne peut pas être modifiée.');
        });
    }

    /** @return BelongsTo<ErasureRequest, $this> */
    public function erasureRequest(): BelongsTo
    {
        return $this->belongsTo(ErasureRequest::class);
    }
}
