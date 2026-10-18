<?php

namespace App\Enums;

enum EventType: string
{
    case Created = 'created';
    case Sent = 'sent';
    case SendFailed = 'send_failed'; // ajout au cahier des charges : l'utilisateur doit savoir si Gmail a échoué
    case ReminderSent = 'reminder_sent';
    case Extended = 'extended';
    case ResponseAccepted = 'response_accepted';
    case ResponseRefused = 'response_refused';
    case Overdue = 'overdue';
    case ComplaintGenerated = 'complaint_generated';
    case Closed = 'closed';
    // V2 : vérification de l'effacement.
    case ErasureVerified = 'erasure_verified';
    case ErasureStillPresent = 'erasure_still_present';
    case VerificationImpossible = 'verification_impossible';

    /**
     * Événement enregistré automatiquement quand la demande entre dans un statut.
     */
    public static function forStatus(RequestStatus $status): self
    {
        return match ($status) {
            RequestStatus::Draft => self::Created,
            RequestStatus::Sent => self::Sent,
            RequestStatus::Extended => self::Extended,
            RequestStatus::Accepted => self::ResponseAccepted,
            RequestStatus::Refused => self::ResponseRefused,
            RequestStatus::Overdue => self::Overdue,
            RequestStatus::Complaint => self::ComplaintGenerated,
            RequestStatus::Closed => self::Closed,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Demande créée',
            self::Sent => 'Demande envoyée',
            self::SendFailed => 'Échec de l\'envoi',
            self::ReminderSent => 'Relance envoyée',
            self::Extended => 'Prolongation du délai annoncée',
            self::ResponseAccepted => 'Réponse : demande acceptée',
            self::ResponseRefused => 'Réponse : demande refusée',
            self::Overdue => 'Délai légal dépassé',
            self::ComplaintGenerated => 'Dossier de plainte CNIL préparé',
            self::Closed => 'Demande clôturée',
            self::ErasureVerified => 'Effacement vérifié : les données ne sont plus en ligne',
            self::ErasureStillPresent => "Données toujours en ligne malgré l'acceptation",
            self::VerificationImpossible => 'Vérification automatique impossible',
        };
    }
}
