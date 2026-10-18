<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\DataType;
use App\Enums\EventType;
use App\Enums\LegalGround;
use App\Enums\RequestKind;
use App\Enums\RequestStatus;
use App\Enums\VerificationStatus;
use App\Exceptions\InvalidTransitionException;
use Database\Factories\ErasureRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Une demande d'effacement (art. 17) / d'opposition (art. 21).
 *
 * "status", "sent_at" et "due_at" ne sont volontairement PAS dans Fillable :
 * ils ne changent que via transitionTo().
 *
 * @property int $id
 * @property int $user_id
 * @property int $organization_id
 * @property RequestKind $kind
 * @property string|null $details
 * @property string $url
 * @property Collection<int, DataType> $data_types
 * @property Collection<int, LegalGround> $legal_grounds
 * @property Channel $channel
 * @property RequestStatus $status
 * @property string $subject
 * @property string $body
 * @property Carbon|null $sent_at
 * @property Carbon|null $due_at
 * @property VerificationStatus|null $verification
 * @property Carbon|null $next_check_at
 * @property int $check_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Organization $organization
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RequestEvent> $events
 */
#[Fillable(['organization_id', 'kind', 'url', 'data_types', 'legal_grounds', 'details', 'channel', 'subject', 'body'])]
class ErasureRequest extends Model
{
    /** @use HasFactory<ErasureRequestFactory> */
    use HasFactory, Prunable;

    /** Relations chargées pour la page de détail (une seule liste, utilisée partout). */
    public const DETAIL_RELATIONS = ['organization', 'events', 'checks'];

    /** Vrai uniquement pendant l'exécution de transitionTo(). */
    private bool $transitioning = false;

    protected $attributes = [
        'status' => 'draft',
        'kind' => 'erasure',
    ];

    protected function casts(): array
    {
        return [
            // Colonne JSON <-> collection d'enums : ["name","email"] <-> Collection<DataType>
            'data_types' => AsEnumCollection::of(DataType::class),
            'legal_grounds' => AsEnumCollection::of(LegalGround::class),
            'kind' => RequestKind::class,
            'details' => 'encrypted',
            'channel' => Channel::class,
            'status' => RequestStatus::class,
            'sent_at' => 'datetime',
            'due_at' => 'datetime',
            'verification' => VerificationStatus::class,
            'next_check_at' => 'datetime',
            'check_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Garde-fou : toute modification du statut hors de transitionTo() est un bug.
        static::updating(function (ErasureRequest $request) {
            if ($request->isDirty('status') && ! $request->transitioning) {
                throw new LogicException('Le statut ne peut changer que via transitionTo().');
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<RequestEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(RequestEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    /**
     * Seul point d'entrée pour changer de statut : vérifie la transition,
     * applique les attributs liés (sent_at, due_at…) et enregistre l'événement,
     * le tout dans une transaction.
     *
     * @param  array<string, mixed>  $attributes  colonnes à modifier en même temps (ex. sent_at, due_at)
     * @param  array<string, mixed>  $payload  détails stockés dans l'historique
     */
    public function transitionTo(RequestStatus $to, array $attributes = [], array $payload = []): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw InvalidTransitionException::between($this->status, $to);
        }

        // Effacement accepté : on programme la vérification que la page ne contient plus les données.
        if ($to === RequestStatus::Accepted && $this->kind === RequestKind::Erasure) {
            $attributes += [
                'verification' => VerificationStatus::Pending,
                'next_check_at' => now()->addDays((int) config('scanner.verification.first_delay_days')),
                'check_count' => 0,
            ];
        }

        DB::transaction(function () use ($to, $attributes, $payload) {
            $this->transitioning = true;

            try {
                $this->forceFill([...$attributes, 'status' => $to])->save();
            } finally {
                $this->transitioning = false;
            }

            $this->recordEvent(EventType::forStatus($to), $payload);
        });
    }

    /**
     * Ajoute une ligne à l'historique sans changer le statut (création, relance, échec d'envoi…).
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordEvent(EventType $type, array $payload = []): RequestEvent
    {
        return $this->events()->create([
            'type' => $type,
            'payload' => $payload === [] ? null : $payload,
        ]);
    }

    public function isDraft(): bool
    {
        return $this->status === RequestStatus::Draft;
    }

    public function hasComplaint(): bool
    {
        if ($this->status === RequestStatus::Complaint) {
            return true;
        }

        // Historique déjà chargé : on évite une requête SQL supplémentaire.
        return $this->relationLoaded('events')
            ? $this->events->contains('type', EventType::ComplaintGenerated)
            : $this->events()->where('type', EventType::ComplaintGenerated)->exists();
    }

    /**
     * Plainte CNIL : refus ou délai dépassé ; après une acceptation, seulement si la vérification
     * montre que les données sont toujours en ligne.
     */
    public function canFileComplaint(): bool
    {
        if (! $this->status->canTransitionTo(RequestStatus::Complaint)) {
            return false;
        }

        return $this->status !== RequestStatus::Accepted || $this->verification === VerificationStatus::StillPresent;
    }

    /**
     * Vérification manuelle possible tant qu'un effacement a été accepté (y compris pour revérifier).
     */
    public function canBeVerified(): bool
    {
        return $this->kind === RequestKind::Erasure
            && $this->verification !== null
            && in_array($this->status, [RequestStatus::Accepted, RequestStatus::Complaint], true);
    }

    /** @return HasMany<ErasureCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(ErasureCheck::class)->orderByDesc('checked_at')->orderByDesc('id');
    }

    /**
     * Demandes dont une vérification automatique est due (tâche quotidienne).
     *
     * @param  Builder<ErasureRequest>  $query
     */
    public function scopeDueForVerification(Builder $query): void
    {
        $query->where('verification', VerificationStatus::Pending)->where('next_check_at', '<=', now());
    }

    /**
     * Actions proposées à l'utilisateur selon le statut. Le front se contente
     * d'afficher les boutons correspondants : la règle métier reste ici.
     *
     * @return list<string>
     */
    public function availableActions(): array
    {
        $status = $this->status;
        $actions = [];

        if ($status === RequestStatus::Draft) {
            $actions[] = 'edit';
            $actions[] = $this->channel === Channel::Gmail ? 'send' : 'mark_sent';
        }
        if ($status === RequestStatus::Overdue) {
            $actions[] = 'reminder';
        }
        if ($this->responseOutcomes() !== []) {
            $actions[] = 'respond';
        }
        if ($this->canFileComplaint()) {
            $actions[] = 'complaint';
        }
        if ($this->canBeVerified()) {
            $actions[] = 'verify';
        }
        if ($status->canTransitionTo(RequestStatus::Closed)) {
            $actions[] = 'close';
        }
        if ($this->hasComplaint()) {
            $actions[] = 'download_complaint';
        }
        $actions[] = 'delete';

        return $actions;
    }

    /**
     * Réponses de l'organisme que l'on peut saisir dans le statut actuel.
     *
     * @return list<string>
     */
    public function responseOutcomes(): array
    {
        // Une réponse n'a de sens qu'une fois la demande envoyée.
        if (! in_array($this->status, [RequestStatus::Sent, RequestStatus::Extended, RequestStatus::Overdue], true)) {
            return [];
        }

        $outcomes = [];
        foreach (['accepted' => RequestStatus::Accepted, 'refused' => RequestStatus::Refused, 'extended' => RequestStatus::Extended] as $outcome => $status) {
            if ($this->status->canTransitionTo($status)) {
                $outcomes[] = $outcome;
            }
        }

        return $outcomes;
    }

    /**
     * Demandes dont le délai légal est dépassé (utilisé par CheckDeadlinesCommand).
     *
     * @param  Builder<ErasureRequest>  $query
     */
    public function scopePastDue(Builder $query): void
    {
        $query->whereIn('status', RequestStatus::awaitingResponse())->where('due_at', '<', now());
    }

    /**
     * Conservation limitée : les demandes clôturées depuis plus de 12 mois sont supprimées
     * par "php artisan model:prune" (planifiée chaque jour dans routes/console.php).
     *
     * @return Builder<ErasureRequest>
     */
    public function prunable(): Builder
    {
        return static::where('status', RequestStatus::Closed)->where('updated_at', '<', now()->subMonths(12));
    }
}
