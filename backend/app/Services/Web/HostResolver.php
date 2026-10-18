<?php

namespace App\Services\Web;

/**
 * Résolution DNS derrière une interface : les tests remplacent le vrai DNS par une table fixe,
 * sans aucun accès réseau.
 */
interface HostResolver
{
    /**
     * @return list<string> adresses IPv4 et IPv6 du nom d'hôte (liste vide si introuvable)
     */
    public function resolve(string $host): array;
}
