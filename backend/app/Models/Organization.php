<?php

namespace App\Models;

use App\Enums\OrganizationCategory;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Le « responsable de traitement » : l'organisme qui gère le site.
 *
 * @property int $id
 * @property string $name
 * @property string $domain
 * @property OrganizationCategory $category
 * @property string|null $privacy_email
 * @property string|null $privacy_form_url
 * @property string|null $dpo_name
 * @property Carbon|null $verified_at
 * @property int|null $created_by
 */
#[Fillable(['name', 'domain', 'category', 'privacy_email', 'privacy_form_url', 'dpo_name'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => OrganizationCategory::class,
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ErasureRequest, $this> */
    public function erasureRequests(): HasMany
    {
        return $this->hasMany(ErasureRequest::class);
    }

    /**
     * Recherche par nom ou par domaine.
     *
     * @param  Builder<Organization>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if ($term === null || trim($term) === '') {
            return;
        }

        // On échappe % et _ pour qu'ils soient cherchés littéralement dans le LIKE.
        $like = '%'.addcslashes(trim($term), '%_\\').'%';
        $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('domain', 'like', $like));
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
