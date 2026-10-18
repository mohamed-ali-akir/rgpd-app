<?php

namespace App\Services\Web;

use Illuminate\Support\Facades\Cache;

/**
 * Respect de robots.txt : on ne lit pas une page qu'un site interdit aux robots.
 * Le fichier est mis en cache par site (24 h, ou 1 h après une erreur).
 */
class RobotsPolicy
{
    public function __construct(private readonly SafeHttpClient $http) {}

    public function allows(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }
        $origin = strtolower($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''));

        return $this->rulesFor($origin)->allows($url);
    }

    private function rulesFor(string $origin): RobotsTxt
    {
        $key = 'robots:'.hash('sha256', $origin);
        /** @var array{status: string, content: string}|null $cached */
        $cached = Cache::get($key);

        if ($cached === null) {
            $cached = $this->download($origin);
            $ttl = $cached['status'] === 'ok' || $cached['status'] === 'none'
                ? (int) config('scanner.fetch.robots_cache_seconds')
                : (int) config('scanner.fetch.robots_error_cache_seconds');
            Cache::put($key, $cached, $ttl);
        }

        return match ($cached['status']) {
            'ok' => RobotsTxt::parse($cached['content'], (string) config('scanner.fetch.robots_token')),
            'none' => RobotsTxt::allowAll(),
            default => RobotsTxt::disallowAll(),
        };
    }

    /**
     * Convention de la norme : pas de robots.txt (4xx) = tout est permis ;
     * serveur en erreur (5xx) ou injoignable = on s'abstient par prudence.
     *
     * @return array{status: string, content: string}
     */
    private function download(string $origin): array
    {
        try {
            $response = $this->http->get($origin.'/robots.txt', 'text/plain');
        } catch (FetchException $e) {
            return ['status' => $e->failure === FetchFailure::TooLarge ? 'none' : 'error', 'content' => ''];
        }

        return match (true) {
            $response['status'] >= 200 && $response['status'] < 300 => ['status' => 'ok', 'content' => $response['body']],
            $response['status'] >= 400 && $response['status'] < 500 => ['status' => 'none', 'content' => ''],
            default => ['status' => 'error', 'content' => ''],
        };
    }
}
