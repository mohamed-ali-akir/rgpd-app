<?php

namespace App\Models;

use App\Enums\ScanStatus;
use App\Enums\ScanTrigger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Une recherche des données publiques de l'utilisateur.
 *
 * @property int $id
 * @property int $user_id
 * @property ScanTrigger $trigger
 * @property ScanStatus $status
 * @property array<string, array{status: string, found?: int, error?: string}>|null $report
 * @property int $new_findings_count
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property-read User $user
 */
#[Fillable(['trigger', 'status', 'report', 'new_findings_count', 'started_at', 'finished_at'])]
class Scan extends Model
{
    use Prunable;

    protected $attributes = [
        'status' => 'queued',
        'new_findings_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'trigger' => ScanTrigger::class,
            'status' => ScanStatus::class,
            'report' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Finding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    /**
     * @param  Builder<Scan>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', ScanStatus::active());
    }

    /**
     * Les scans ne servent qu'à l'historique : purgés après 12 mois (les résultats restent).
     *
     * @return Builder<Scan>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths((int) config('scanner.scan.scans_retention_months')));
    }
}
