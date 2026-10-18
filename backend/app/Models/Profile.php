<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Identité de l'utilisateur, utilisée pour remplir les lettres.
 * Toutes les colonnes sont chiffrées en base avec APP_KEY (cast "encrypted").
 *
 * @property int $id
 * @property int $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $postal_address
 * @property string|null $phone
 * @property bool $auto_scan
 * @property-read User $user
 */
#[Fillable(['first_name', 'last_name', 'postal_address', 'phone', 'auto_scan'])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'first_name' => 'encrypted',
            'last_name' => 'encrypted',
            'postal_address' => 'encrypted',
            'phone' => 'encrypted',
            'auto_scan' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
