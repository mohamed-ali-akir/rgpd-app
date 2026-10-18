<?php

namespace App\Models;

use App\Enums\DataType;
use App\Enums\FindingSource;
use App\Enums\FindingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Une page trouvée par le scanner, qui semble publier des données de l'utilisateur.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $scan_id
 * @property FindingSource $source
 * @property string $url
 * @property string $url_hash
 * @property string $domain
 * @property int|null $organization_id
 * @property string|null $title
 * @property string|null $snippet
 * @property array<string, mixed>|null $meta
 * @property Collection<int, DataType> $matched
 * @property int $confidence
 * @property FindingStatus $status
 * @property int|null $erasure_request_id
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $decided_at
 * @property-read Organization|null $organization
 * @property-read ErasureRequest|null $erasureRequest
 */
#[Fillable(['source', 'url', 'url_hash', 'domain', 'organization_id', 'title', 'snippet', 'meta', 'matched', 'confidence', 'first_seen_at', 'last_seen_at'])]
class Finding extends Model
{
    use Prunable;

    protected $attributes = [
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'source' => FindingSource::class,
            'status' => FindingStatus::class,
            // Titre, extrait et détails parlent de l'utilisateur : chiffrés en base.
            'title' => 'encrypted',
            'snippet' => 'encrypted',
            'meta' => 'encrypted:array',
            'matched' => AsEnumCollection::of(DataType::class),
            'confidence' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<ErasureRequest, $this> */
    public function erasureRequest(): BelongsTo
    {
        return $this->belongsTo(ErasureRequest::class);
    }

    public function decide(FindingStatus $status): void
    {
        $this->forceFill([
            'status' => $status,
            'decided_at' => $status->isDecided() ? now() : null,
        ])->save();
    }

    /**
     * Minimisation : les résultats écartés ("pas moi", "ignoré") sont supprimés après 6 mois.
     *
     * @return Builder<Finding>
     */
    public function prunable(): Builder
    {
        return static::whereIn('status', [FindingStatus::Rejected, FindingStatus::Ignored])
            ->where('decided_at', '<', now()->subMonths((int) config('scanner.scan.dismissed_findings_retention_months')));
    }
}
