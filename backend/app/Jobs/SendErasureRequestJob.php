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
use App\Services\Mail\MailSendingFailedException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
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

    /** @var list<int> */
    public array $backoff = [60, 300];

    /**
     * Demande (ou compte) supprimée avant que le worker ne traite le job : le modèle ne peut plus
     * être relu depuis la file. Sans cette option, le job échouait sur une ModelNotFoundException
     * et finissait dans failed_jobs ; avec, il est simplement abandonné.
     */
    public bool $deleteWhenMissingModels = true;

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

        // Un essai précédent a commencé l'envoi sans pouvoir l'enregistrer (base indisponible juste après
        // l'appel à Gmail, worker arrêté en plein envoi…) : Gmail a peut-être déjà envoyé la lettre.
        // On ne la renvoie surtout pas : l'utilisateur vérifie ses messages envoyés et relance s'il le faut.
        if (! $this->startSending($request)) {
            $this->stopSending($request);
            $request->recordEvent(EventType::SendFailed, ['kind' => $this->kind, 'reason' => 'uncertain']);

            return;
        }

        [$subject, $body] = $this->kind === self::REMINDER
            ? $this->reminderLetter($request, $letters)
            : [$request->subject, $request->body];

        try {
            $messageId = $sender->send($request->user, $request->organization->privacy_email, $subject, $body);
        } catch (GoogleAccountDisconnectedException) {
            // Inutile de réessayer : il faut que l'utilisateur reconnecte Gmail.
            $this->stopSending($request);
            $request->user->googleAccount?->delete();
            $request->recordEvent(EventType::SendFailed, ['kind' => $this->kind, 'reason' => 'google_disconnected']);

            return;
        } catch (MailSendingFailedException $exception) {
            // Gmail a répondu par une erreur : rien n'est parti, le prochain essai peut envoyer.
            $this->stopSending($request);

            throw $exception;
        }

        // Toute autre erreur à partir d'ici laisse le marqueur en place : le prochain essai saura
        // que la lettre est peut-être partie.
        DB::transaction(function () use ($request, $messageId, $deadlines) {
            if ($this->kind === self::REMINDER) {
                $request->recordEvent(EventType::ReminderSent, ['channel' => 'gmail', 'message_id' => $messageId]);
            } else {
                $sentAt = now();
                $request->transitionTo(RequestStatus::Sent, [
                    'sent_at' => $sentAt,
                    'due_at' => $deadlines->initialDueAt($sentAt),
                ], ['channel' => 'gmail', 'message_id' => $messageId]);
            }

            $this->stopSending($request);
        });
    }

    /**
     * Appelé par Laravel quand tous les essais ont échoué.
     */
    public function failed(?Throwable $exception): void
    {
        $request = $this->erasureRequest->fresh();
        if ($request === null) {
            return;
        }

        // Marqueur encore posé : le dernier essai a échoué APRÈS avoir lancé l'envoi.
        $uncertain = $request->sending_started_at !== null;
        $this->stopSending($request);
        $request->recordEvent(EventType::SendFailed, ['kind' => $this->kind, 'reason' => $uncertain ? 'uncertain' : 'gmail_error']);
    }

    /**
     * Pose le marqueur « envoi commencé » juste avant l'appel à Gmail. Requête atomique : renvoie false
     * si le marqueur était déjà posé par un essai précédent.
     */
    private function startSending(ErasureRequest $request): bool
    {
        return ErasureRequest::query()
            ->whereKey($request->id)
            ->whereNull('sending_started_at')
            ->update(['sending_started_at' => now()]) === 1;
    }

    private function stopSending(ErasureRequest $request): void
    {
        ErasureRequest::query()->whereKey($request->id)->update(['sending_started_at' => null]);
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
