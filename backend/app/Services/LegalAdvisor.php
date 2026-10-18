<?php

namespace App\Services;

use App\Enums\Channel;
use App\Enums\LegalGround;
use App\Enums\OrganizationCategory;
use App\Enums\RequestKind;
use App\Models\Organization;

/**
 * Règles déterministes (aucune IA) : organisme + origine des données
 * → fondements juridiques suggérés, avertissements, canal conseillé.
 *
 * Les avertissements informent mais ne bloquent JAMAIS l'envoi.
 */
class LegalAdvisor
{
    public function advise(
        Organization $organization,
        bool $selfPublished = false,
        bool $hasAccount = false,
        RequestKind $kind = RequestKind::Erasure,
    ): LegalAdvice {
        return new LegalAdvice(
            legalGrounds: $kind === RequestKind::Erasure ? $this->erasureGrounds($selfPublished, $hasAccount) : [],
            warnings: $this->warnings($organization, $kind),
            suggestedChannel: $this->suggestedChannel($organization),
        );
    }

    /**
     * Les motifs de l'article 17.1 ne concernent que l'effacement.
     *
     * @return list<LegalGround>
     */
    private function erasureGrounds(bool $selfPublished, bool $hasAccount): array
    {
        // Par défaut : opposition (art. 21) + effacement (art. 17.1.c).
        $grounds = [LegalGround::Objection];

        // L'utilisateur a publié lui-même ou créé un compte : il peut retirer son consentement.
        if ($selfPublished || $hasAccount) {
            $grounds[] = LegalGround::ConsentWithdrawn;
        }

        return $grounds;
    }

    /**
     * Ne dépend que de l'organisme et du droit exercé : recalculé à chaque affichage, donc jamais stocké.
     *
     * @return list<array{code: string, message: string}>
     */
    public function warnings(Organization $organization, RequestKind $kind = RequestKind::Erasure): array
    {
        // Les exceptions de l'art. 17.3 et le déréférencement ne concernent que l'effacement.
        $warnings = $kind !== RequestKind::Erasure ? [] : match ($organization->category) {
            OrganizationCategory::LegalRegistry => [[
                'code' => 'legal_publication',
                'message' => 'Publication légale : un refus est probable (exception de l\'art. 17.3.b, obligation légale de publication).',
            ]],
            OrganizationCategory::Press => [[
                'code' => 'freedom_of_expression',
                'message' => 'Liberté d\'expression et d\'information (art. 17.3.a) : l\'organe de presse peut refuser. Une demande de déréférencement auprès des moteurs de recherche est souvent plus efficace.',
            ]],
            OrganizationCategory::SearchEngine => [[
                'code' => 'dereferencing_form',
                'message' => 'Les moteurs de recherche traitent les demandes de déréférencement via leur propre formulaire : envoyez la demande vous-même depuis ce formulaire.',
            ]],
            default => [],
        };

        if (! $organization->isVerified()) {
            $warnings[] = [
                'code' => 'unverified_contact',
                'message' => 'Les coordonnées de cet organisme n\'ont pas été vérifiées. Vérifiez l\'adresse sur sa politique de confidentialité avant d\'envoyer.',
            ];
        }

        return $warnings;
    }

    public function suggestedChannel(Organization $organization): Channel
    {
        if ($organization->category === OrganizationCategory::SearchEngine || $organization->privacy_email === null) {
            return Channel::Manual;
        }

        return Channel::Gmail;
    }
}
