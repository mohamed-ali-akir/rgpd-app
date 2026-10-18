<?php

namespace App\Http\Controllers\Api;

use App\Enums\Channel;
use App\Enums\EventType;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\MarkSentRequest;
use App\Http\Requests\StoreResponseRequest;
use App\Http\Resources\ErasureRequestResource;
use App\Jobs\SendErasureRequestJob;
use App\Models\ErasureRequest;
use App\Models\User;
use App\Services\DeadlineCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use LogicException;

/**
 * Actions qui font avancer une demande dans la machine à états.
 * Chaque changement de statut passe par $erasureRequest->transitionTo().
 */
class ErasureRequestActionController extends Controller
{
    /** Anti-abus : 10 envois Gmail (demandes + relances) par jour et par utilisateur. */
    public const DAILY_GMAIL_LIMIT = 10;

    /**
     * Canal gmail : met le job en file. Le statut passera à "sent" quand le worker aura envoyé.
     */
    public function send(Request $request, ErasureRequest $erasureRequest): JsonResponse
    {
        abort_unless($erasureRequest->isDraft(), 409, 'Cette demande a déjà été envoyée.');
        abort_unless($erasureRequest->channel === Channel::Gmail, 409, 'Cette demande est à envoyer vous-même (canal manuel).');
        $this->ensureCanSendWithGmail($request->user(), $erasureRequest);

        SendErasureRequestJob::dispatch($erasureRequest);

        // 202 Accepted : la demande est prise en compte, mais pas encore traitée.
        return $this->detail($erasureRequest)->response()->setStatusCode(202);
    }

    /**
     * Canal manual : l'utilisateur confirme qu'il a envoyé la lettre lui-même.
     */
    public function markSent(MarkSentRequest $request, ErasureRequest $erasureRequest, DeadlineCalculator $deadlines): ErasureRequestResource
    {
        abort_unless($erasureRequest->channel === Channel::Manual, 409, 'Cette demande s\'envoie depuis Gmail.');

        $sentAt = $request->filled('sent_at') ? Carbon::parse($request->input('sent_at')) : now();

        $erasureRequest->transitionTo(RequestStatus::Sent, [
            'sent_at' => $sentAt,
            'due_at' => $deadlines->initialDueAt($sentAt),
        ], ['channel' => 'manual']);

        return $this->detail($erasureRequest);
    }

    /**
     * Relance quand le délai est dépassé. Ne change pas le statut : ajoute un événement.
     */
    public function reminder(Request $request, ErasureRequest $erasureRequest): JsonResponse
    {
        abort_unless($erasureRequest->status === RequestStatus::Overdue, 409, 'Une relance n\'est possible qu\'une fois le délai dépassé.');

        if ($erasureRequest->channel === Channel::Gmail) {
            $this->ensureCanSendWithGmail($request->user(), $erasureRequest);
            SendErasureRequestJob::dispatch($erasureRequest, SendErasureRequestJob::REMINDER);

            return $this->detail($erasureRequest)->response()->setStatusCode(202);
        }

        // Canal manuel : l'utilisateur a copié le texte de relance et l'a envoyé lui-même.
        $erasureRequest->recordEvent(EventType::ReminderSent, ['channel' => 'manual']);

        return $this->detail($erasureRequest)->response();
    }

    /**
     * L'utilisateur saisit la réponse reçue (l'app ne lit pas sa boîte Gmail).
     */
    public function response(StoreResponseRequest $request, ErasureRequest $erasureRequest, DeadlineCalculator $deadlines): ErasureRequestResource
    {
        $payload = array_filter(['note' => $request->input('note')]);

        match ($request->string('outcome')->toString()) {
            'accepted' => $erasureRequest->transitionTo(RequestStatus::Accepted, [], $payload),
            'refused' => $erasureRequest->transitionTo(RequestStatus::Refused, [], $payload),
            // Prolongation annoncée : le délai passe à 3 mois après l'envoi (art. 12.3).
            'extended' => $erasureRequest->transitionTo(RequestStatus::Extended, [
                'due_at' => $deadlines->extendedDueAt($erasureRequest->sent_at ?? throw new LogicException('Demande prolongée sans date d\'envoi.')),
            ], $payload),
            // Inatteignable : StoreResponseRequest n'accepte que les valeurs ci-dessus.
            default => throw new LogicException('Réponse inconnue.'),
        };

        return $this->detail($erasureRequest);
    }

    public function complaint(ErasureRequest $erasureRequest): ErasureRequestResource
    {
        $erasureRequest->transitionTo(RequestStatus::Complaint);

        return $this->detail($erasureRequest);
    }

    public function close(ErasureRequest $erasureRequest): ErasureRequestResource
    {
        $erasureRequest->transitionTo(RequestStatus::Closed);

        return $this->detail($erasureRequest);
    }

    /**
     * Vérifications communes à tout envoi via Gmail.
     */
    private function ensureCanSendWithGmail(User $user, ErasureRequest $erasureRequest): void
    {
        abort_unless($user->hasVerifiedEmail(), 403, 'Vérifiez votre adresse e-mail avant d\'envoyer une demande.');
        abort_if($user->googleAccount === null, 422, 'Connectez votre compte Gmail dans les paramètres avant d\'envoyer.');
        abort_if($erasureRequest->organization->privacy_email === null, 422, 'Cet organisme n\'a pas d\'adresse e-mail de contact.');

        $key = 'gmail-send:'.$user->id;
        abort_if(
            RateLimiter::tooManyAttempts($key, self::DAILY_GMAIL_LIMIT),
            429,
            'Limite de '.self::DAILY_GMAIL_LIMIT.' envois par jour atteinte. Réessayez demain.',
        );
        RateLimiter::hit($key, 60 * 60 * 24);
    }

    private function detail(ErasureRequest $erasureRequest): ErasureRequestResource
    {
        return new ErasureRequestResource($erasureRequest->refresh()->load('organization', 'events'));
    }
}
