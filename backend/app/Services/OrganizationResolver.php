<?php

namespace App\Services;

use App\Models\Organization;
use InvalidArgumentException;

/**
 * URL → domaine → organisme connu (ou null : l'utilisateur devra le créer).
 */
class OrganizationResolver
{
    /**
     * @return array{domain: string, organization: Organization|null}
     */
    public function resolve(string $url): array
    {
        $domain = $this->extractDomain($url);

        return [
            'domain' => $domain,
            'organization' => $this->findByDomain($domain),
        ];
    }

    /**
     * "https://www.Exemple.fr/profil/42?x=1" → "exemple.fr"
     */
    public function extractDomain(string $url): string
    {
        $url = trim($url);
        // parse_url() a besoin d'un schéma pour reconnaître l'hôte.
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || ! str_contains($host, '.')) {
            throw new InvalidArgumentException('URL invalide.');
        }

        $host = strtolower(rtrim($host, '.'));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * Cherche le domaine exact, puis les domaines parents :
     * "fr.linkedin.com" → "fr.linkedin.com", puis "linkedin.com".
     */
    public function findByDomain(string $domain): ?Organization
    {
        $parts = explode('.', $domain);
        $candidates = [];
        // On s'arrête à 2 segments : on ne cherche jamais "com" ou "fr" seuls.
        for ($i = 0; $i <= count($parts) - 2; $i++) {
            $candidates[] = implode('.', array_slice($parts, $i));
        }

        // Une seule requête, puis on garde le domaine le plus précis (le plus long).
        return Organization::whereIn('domain', $candidates)
            ->get()
            ->sortByDesc(fn (Organization $o) => strlen($o->domain))
            ->first();
    }
}
