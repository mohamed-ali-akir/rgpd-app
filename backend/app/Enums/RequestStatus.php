<?php

namespace App\Enums;

/**
 * Statuts d'une demande d'effacement et machine à états.
 *
 * La SEULE façon de changer de statut est ErasureRequest::transitionTo(),
 * qui s'appuie sur allowedTransitions() ci-dessous.
 */
enum RequestStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Extended = 'extended';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Overdue = 'overdue';
    case Complaint = 'complaint';
    case Closed = 'closed';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent],
            self::Sent => [self::Extended, self::Accepted, self::Refused, self::Overdue],
            self::Extended => [self::Accepted, self::Refused, self::Overdue],
            // Une relance ajoute un événement mais ne change pas le statut.
            self::Overdue => [self::Accepted, self::Refused, self::Complaint],
            self::Refused => [self::Complaint, self::Closed],
            self::Accepted => [self::Closed],
            self::Complaint => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * Statuts pendant lesquels le délai légal court (vérifiés chaque jour par CheckDeadlinesCommand).
     *
     * @return list<self>
     */
    public static function awaitingResponse(): array
    {
        return [self::Sent, self::Extended];
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Sent => 'Envoyée',
            self::Extended => 'Délai prolongé',
            self::Accepted => 'Acceptée',
            self::Refused => 'Refusée',
            self::Overdue => 'Délai dépassé',
            self::Complaint => 'Plainte CNIL',
            self::Closed => 'Clôturée',
        };
    }
}
