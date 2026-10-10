<?php

namespace App\Services\Mail;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * N'envoie rien : garde les messages en mémoire pour que les tests puissent les vérifier.
 * Enregistrée en singleton, donc la même instance est partagée pendant un test.
 */
class FakeSender implements MailSender
{
    /** @var list<array{from: int, to: string, subject: string, body: string}> */
    public array $sent = [];

    public bool $disconnected = false;

    public function send(User $from, string $to, string $subject, string $body): string
    {
        if ($this->disconnected) {
            throw new GoogleAccountDisconnectedException('Accès Gmail révoqué.');
        }

        $this->sent[] = ['from' => $from->id, 'to' => $to, 'subject' => $subject, 'body' => $body];

        return 'fake-'.Str::uuid();
    }
}
