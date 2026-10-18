<?php

namespace Tests\Support;

use App\Services\Web\HostResolver;

/**
 * DNS de test : chaque nom d'hôte résout vers une IP publique, sauf ceux explicitement configurés.
 * Aucun test ne fait de vraie requête DNS.
 */
class FakeHostResolver implements HostResolver
{
    /** IP publique quelconque (n'est jamais contactée : Http::fake intercepte les requêtes). */
    public const PUBLIC_IP = '93.184.215.14';

    /** @param  array<string, list<string>>  $hosts */
    public function __construct(public array $hosts = []) {}

    public function resolve(string $host): array
    {
        return $this->hosts[$host] ?? [self::PUBLIC_IP];
    }
}
