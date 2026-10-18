<?php

namespace App\Models;

use Database\Factories\GoogleAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Connexion Gmail de l'utilisateur (scope gmail.send uniquement).
 * Le refresh token permet d'obtenir un jeton d'accès valable 1 h, sans redemander l'autorisation.
 *
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property string $refresh_token
 * @property list<string> $scopes
 * @property Carbon $created_at
 * @property-read User $user
 */
#[Fillable(['email', 'refresh_token', 'scopes'])]
#[Hidden(['refresh_token'])]
class GoogleAccount extends Model
{
    /** @use HasFactory<GoogleAccountFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'scopes' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
