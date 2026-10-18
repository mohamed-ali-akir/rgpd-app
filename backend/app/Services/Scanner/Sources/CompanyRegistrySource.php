<?php

namespace App\Services\Scanner\Sources;

use App\Enums\DataType;
use App\Enums\FindingSource;
use App\Services\Identity\Identity;
use App\Services\Scanner\Candidate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Entreprises dont l'utilisateur est (ou a été) dirigeant, via l'API Recherche d'entreprises
 * de l'État (gratuite, sans clé). Ces fiches publient nom, prénoms et date de naissance,
 * repris ensuite par Pappers, Societe.com…
 *
 * Confiance modérée : on ne connaît pas la date de naissance de l'utilisateur, donc un homonyme
 * dirigeant est possible. L'utilisateur confirme toujours.
 */
class CompanyRegistrySource implements ScanSource
{
    private const CONFIDENCE = 60;

    public function key(): FindingSource
    {
        return FindingSource::CompanyRegistry;
    }

    public function scan(Identity $identity): array
    {
        $config = config('scanner.company_registry');

        $response = Http::acceptJson()
            ->timeout((float) $config['timeout'])
            ->retry([500, 1500], throw: false)
            ->get($config['url'], [
                'q' => $identity->fullName(),
                'nom_personne' => $identity->lastName,
                'prenoms_personne' => $identity->firstName,
                'type_personne' => 'dirigeant',
                'per_page' => $config['per_page'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('API Recherche d\'entreprises : HTTP '.$response->status());
        }

        $results = $response->json('results');
        if (! is_array($results)) {
            throw new RuntimeException('API Recherche d\'entreprises : réponse illisible.');
        }

        $candidates = [];
        foreach ($results as $company) {
            if (! is_array($company) || ! isset($company['siren']) || ! is_string($company['siren'])) {
                continue;
            }
            $person = $this->findPerson($identity, $company['dirigeants'] ?? []);
            if ($person === null) {
                continue; // l'API a renvoyé l'entreprise pour un autre dirigeant
            }

            $hasBirth = ! empty($person['date_de_naissance']) || ! empty($person['annee_de_naissance']);
            $role = is_string($person['qualite'] ?? null) ? $person['qualite'] : 'dirigeant';
            $name = is_string($company['nom_complet'] ?? null) ? $company['nom_complet'] : 'Entreprise '.$company['siren'];

            $candidates[] = new Candidate(
                source: FindingSource::CompanyRegistry,
                url: $config['annuaire_url'].$company['siren'],
                title: $name.' (SIREN '.$company['siren'].')',
                snippet: 'Vous y figurez comme '.Str::lower($role).($hasBirth ? ', avec votre date de naissance.' : '.'),
                dataTypes: $hasBirth ? [DataType::Name, DataType::BirthDate] : [DataType::Name],
                confidence: self::CONFIDENCE,
                meta: ['siren' => $company['siren'], 'company' => $name, 'role' => $role],
            );
        }

        return $candidates;
    }

    /**
     * Le dirigeant "personne physique" dont le nom et un des prénoms correspondent à l'utilisateur.
     *
     * @return array<string, mixed>|null
     */
    private function findPerson(Identity $identity, mixed $directors): ?array
    {
        if (! is_array($directors)) {
            return null;
        }

        $last = $this->words($identity->lastName);
        $first = $this->words($identity->firstName);

        foreach ($directors as $director) {
            if (! is_array($director) || ! is_string($director['nom'] ?? null) || ! is_string($director['prenoms'] ?? null)) {
                continue;
            }
            // "prenoms" contient tous les prénoms : "Camille Marie Louise".
            if ($this->words($director['nom']) === $last
                && str_contains(' '.$this->words($director['prenoms']).' ', ' '.$first.' ')) {
                return $director;
            }
        }

        return null;
    }

    private function words(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text))));
    }
}
