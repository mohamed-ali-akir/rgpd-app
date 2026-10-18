<?php

namespace App\Services\Demo;

use App\Enums\DataType;
use App\Enums\FindingSource;
use App\Services\Identity\Identity;
use App\Services\Scanner\Candidate;
use App\Services\Scanner\Sources\ScanSource;

/**
 * Mode démo : résultats fictifs sur des domaines réservés (".example", RFC 2606), sans aucun accès réseau.
 * Interroger les vraies sources avec un nom de démo pourrait faire apparaître de vraies personnes.
 */
class DemoScanSource implements ScanSource
{
    public function key(): FindingSource
    {
        return FindingSource::WebSearch;
    }

    public function scan(Identity $identity): array
    {
        $name = $identity->fullName();

        return [
            new Candidate(FindingSource::WebSearch, 'https://annuaire-demo.example/fiche/camille-demo', "{$name} — Annuaire Démo",
                "{$name}, 12 rue des Exemples, 75000 Paris. Tél. 06 00 00 00 00", [DataType::Name, DataType::Phone, DataType::PostalAddress], 90, ['verified' => true]),
            new Candidate(FindingSource::WebSearch, 'https://forum-demo.example/membres/camille', 'Profil membre — Forum Démo',
                "Membre : {$name}, contact : ".($identity->emails[0] ?? 'demo@example.test'), [DataType::Name, DataType::Email], 97, ['verified' => true]),
            new Candidate(FindingSource::CompanyRegistry, 'https://registre-demo.example/entreprise/000000000', 'DEMO CONSEIL (SIREN 000 000 000)',
                'Vous y figurez comme gérant, avec votre date de naissance.', [DataType::Name, DataType::BirthDate], 60,
                ['siren' => '000000000', 'company' => 'DEMO CONSEIL', 'role' => 'Gérant']),
            // Un homonyme, pour montrer le bouton « Ce n'est pas moi ».
            new Candidate(FindingSource::WebSearch, 'https://club-demo.example/equipe', 'Équipe — Club Démo',
                "{$name}, capitaine de l'équipe de volley", [DataType::Name], 35, ['verified' => false, 'unverified_reason' => 'robots_disallowed']),
        ];
    }
}
