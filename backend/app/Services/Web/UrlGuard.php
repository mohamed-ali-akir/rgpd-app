<?php

namespace App\Services\Web;

/**
 * Protection SSRF (Server-Side Request Forgery).
 *
 * Le serveur lit des URL fournies par les utilisateurs. Sans contrôle, quelqu'un pourrait lui faire
 * lire http://127.0.0.1/…, l'adresse de métadonnées du cloud (169.254.169.254) ou une machine du réseau
 * interne. On n'autorise donc que :
 *  - http et https, sur les ports 80 et 443, sans identifiants dans l'URL ;
 *  - des noms d'hôte dont TOUTES les adresses IP sont publiques.
 */
class UrlGuard
{
    private const ALLOWED_PORTS = ['http' => 80, 'https' => 443];

    public function __construct(private readonly HostResolver $resolver) {}

    /**
     * @throws UnsafeUrlException
     */
    public function check(string $url): GuardedUrl
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new UnsafeUrlException('URL invalide.');
        }

        $scheme = strtolower($parts['scheme']);
        if (! isset(self::ALLOWED_PORTS[$scheme])) {
            throw new UnsafeUrlException('Seuls les liens http et https sont lus.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrlException('Les identifiants dans une URL ne sont pas acceptés.');
        }

        $port = $parts['port'] ?? self::ALLOWED_PORTS[$scheme];
        if ($port !== self::ALLOWED_PORTS[$scheme]) {
            throw new UnsafeUrlException('Port non autorisé.');
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolver->resolve($host);
        if ($ips === []) {
            throw new UnsafeUrlException('Nom de domaine introuvable.');
        }

        foreach ($ips as $ip) {
            if (! $this->isPublic($ip)) {
                throw new UnsafeUrlException('Adresse non publique refusée.');
            }
        }

        return new GuardedUrl($url, $scheme, $host, $port, $ips[0]);
    }

    /**
     * Refuse les plages privées (10/8, 172.16/12, 192.168/16, fc00::/7…) et réservées
     * (127/8, 0/8, 169.254/16, ::1, fe80::/10…). IPv6 contenant une IPv4 (::ffff:127.0.0.1) : on teste l'IPv4.
     */
    public function isPublic(string $ip): bool
    {
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m) === 1) {
            $ip = $m[1];
        }

        // GLOBAL_RANGE (PHP 8.2+) couvre aussi des plages que les deux autres options laissent passer,
        // par exemple 100.64.0.0/10 (réseaux d'opérateurs) ou 192.0.0.0/24.
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE;

        return filter_var($ip, FILTER_VALIDATE_IP, ['flags' => $flags]) !== false;
    }
}
