<?php

namespace App\Jobs;

use App\Enums\EventType;
use App\Enums\RequestStatus;
use App\Models\ErasureRequest;
use App\Services\DeadlineCalculator;
use App\Services\LetterGenerator;
use App\Services\Letters\LetterData;
use App\Services\Mail\GoogleAccountDisconnectedException;
use App\Services\Mail\MailSender;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Envoie une demande (ou une relance) depuis le Gmail de l'utilisateur.
 *
 * Exécuté par le worker ("php artisan queue:work"), donc hors de la requête HTTP :
 * l'utilisateur n'attend pas la réponse de Google, et en cas d'erreur réseau
 * le job est retenté automatiquement.
 */
class SendErasureRequestJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const INITIAL = 'initial';

    public const REMINDER = 'reminder';

    /** 3 essais, espacés de 1 min puis 5 min. */
    public int $tries = 3;

    public array $backoff = [60, 300];

    /**
     * SerializesModels (inclus dans Queueable) ne stocke que l'id de la demande dans la file,
     * pas son contenu : aucune donnée personnelle dans la table "jobs".
     */
    public function __construct(
        public ErasureRequest $erasureRequest,
        public string $kind = self::INITIAL,
    ) {}

    /**
     * ShouldBeUnique : un double clic sur "Envoyer" ne met pas deux jobs en file.
     */
    public function uniqueId(): string
    {
        return $this->erasureRequest->id.'-'.$this->kind;
    }

    public function handle(MailSender $sender, DeadlineCalculator $deadlines, LetterGenerator $letters): void
    {
        $request = $this->erasureRequest->fresh(['user.profile', 'user.googleAccount', 'organization']);

        // La demande a pu être supprimée ou envoyée entre-temps.
        if ($request === null || ! $this->stillRelevant($request)) {
            return;
        }

        [$subject, $body] = $this->kind === self::REMINDER
            ? $this->reminderLetter($request, $letters)
            : [$request->subject, $request->body];

        try {
            $messageId = $sender->send($request->user, $request->organization->privacy_email, $subject, $body);
        } catch (GoogleAccountDisconnectedException) {
            // Inutile de réessayer : il faut que l'utilisateur reconnecte Gmail.
            $request->user->googleAccount?->delete();
            $request->recordEvent(EventType::SendFailed, ['kind' => $this->kind, 'reason' => 'google_disconnected']);

            return;
        }

        if ($this->kind === self::REMINDER) {
            $request->recordEvent(EventType::ReminderSent, ['channel' => 'gmail', 'message_id' => $messageId]);

            return;
        }

        $sentAt = now();
        $request->transitionTo(RequestStatus::Sent, [
            'sent_at' => $sentAt,
            'due_at' => $deadlines->initialDueAt($sentAt),
        ], ['channel' => 'gmail', 'message_id' => $messageId]);
    }

    /**
     * Appelé par Laravel quand tous les essais ont échoué.
     */
    public function failed(?Throwable $exception): void
    {
        $this->erasureRequest->recordEvent(EventType::SendFailed, ['kind' => $this->kind, 'reason' => 'gmail_error']);
    }

    private function stillRelevant(ErasureRequest $request): bool
    {
        return $this->kind === self::REMINDER
            ? $request->status === RequestStatus::Overdue
            : $request->isDraft();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function reminderLetter(ErasureRequest $request, LetterGenerator $letters): array
    {
        $letter = $letters->reminder(LetterData::fromRequest($request), $request->sent_at, $request->due_at);

        return [$letter->subject, $letter->body];
    }
}
