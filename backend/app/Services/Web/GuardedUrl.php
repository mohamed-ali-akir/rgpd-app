<?php

namespace App\Services\Web;

/**
 * URL validée par UrlGuard, avec l'adresse IP publique à utiliser pour s'y connecter.
 */
final readonly class GuardedUrl
{
    public function __construct(
        public string $url,
        public string $scheme,
        public string $host,
        public int $port,
        public string $ip,
    ) {}

    /**
     * Option cURL qui force la connexion sur l'IP vérifiée : le nom d'hôte n'est pas résolu une
     * seconde fois, ce qui empêche une attaque par « DNS rebinding » (une IP publique au moment
     * de la vérification, puis une IP interne au moment de la connexion).
     */
    public function curlResolveEntry(): string
    {
        $ip = str_contains($this->ip, ':') ? '['.$this->ip.']' : $this->ip;

        return "{$this->host}:{$this->port}:{$ip}";
    }
}
