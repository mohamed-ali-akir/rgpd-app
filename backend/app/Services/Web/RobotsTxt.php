<?php

namespace App\Services\Web;

/**
 * Interprète un fichier robots.txt (norme RFC 9309) : groupes par User-agent, règles Allow/Disallow,
 * jokers "*" et ancre de fin "$". La règle la plus longue (la plus précise) l'emporte ; à égalité, Allow gagne.
 * Classe pure, sans réseau : facile à tester.
 */
final class RobotsTxt
{
    /**
     * @param  list<array{allow: bool, path: string}>  $rules
     */
    private function __construct(private readonly array $rules) {}

    public static function allowAll(): self
    {
        return new self([]);
    }

    public static function disallowAll(): self
    {
        return new self([['allow' => false, 'path' => '/']]);
    }

    public static function parse(string $content, string $robotsToken): self
    {
        /** @var array<string, list<array{allow: bool, path: string}>> $groups */
        $groups = [];
        $agents = [];
        $lastWasAgent = false;

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*/', '', $line));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                // Plusieurs lignes User-agent consécutives partagent le même groupe de règles.
                $agents = $lastWasAgent ? [...$agents, strtolower($value)] : [strtolower($value)];
                $lastWasAgent = true;

                continue;
            }
            $lastWasAgent = false;

            if (($field === 'allow' || $field === 'disallow') && $agents !== []) {
                // "Disallow:" vide signifie "tout est autorisé" : on ne l'enregistre pas.
                if ($value === '') {
                    continue;
                }
                foreach ($agents as $agent) {
                    $groups[$agent][] = ['allow' => $field === 'allow', 'path' => $value];
                }
            }
        }

        // Le groupe qui nous nomme explicitement prime sur le groupe générique "*".
        $token = strtolower($robotsToken);
        foreach ($groups as $agent => $rules) {
            if ($agent !== '*' && str_contains($token, $agent)) {
                return new self($rules);
            }
        }

        return new self($groups['*'] ?? []);
    }

    public function allows(string $url): bool
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '/');
        $query = parse_url($url, PHP_URL_QUERY);
        $target = ($path === '' ? '/' : $path).(is_string($query) ? '?'.$query : '');

        $bestLength = -1;
        $allowed = true;
        foreach ($this->rules as $rule) {
            if ($this->matches($rule['path'], $target)) {
                $length = strlen($rule['path']);
                if ($length > $bestLength || ($length === $bestLength && $rule['allow'])) {
                    $bestLength = $length;
                    $allowed = $rule['allow'];
                }
            }
        }

        return $allowed;
    }

    private function matches(string $pattern, string $target): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $pattern = $anchored ? substr($pattern, 0, -1) : $pattern;
        $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).($anchored ? '$' : '').'#';

        return preg_match($regex, $target) === 1;
    }
}
