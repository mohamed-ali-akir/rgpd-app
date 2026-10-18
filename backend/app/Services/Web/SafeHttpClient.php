<?php

namespace App\Services\Web;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Une requête GET unique, sûre et bornée :
 *  - URL vérifiée par UrlGuard, connexion épinglée sur l'IP vérifiée ;
 *  - aucune redirection automatique (chaque étape est revérifiée par l'appelant) ;
 *  - délais courts et taille maximale de réponse.
 */
class SafeHttpClient
{
    public function __construct(private readonly UrlGuard $guard) {}

    /**
     * @return array{status: int, content_type: string, location: string|null, body: string}
     *
     * @throws FetchException
     */
    public function get(string $url, string $accept): array
    {
        try {
            $target = $this->guard->check($url);
        } catch (UnsafeUrlException) {
            throw new FetchException(FetchFailure::UnsafeUrl);
        }

        $maxBytes = (int) config('scanner.fetch.max_bytes');

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('scanner.fetch.user_agent'),
                'Accept' => $accept,
                'Accept-Language' => 'fr,en;q=0.5',
            ])
                ->connectTimeout((float) config('scanner.fetch.connect_timeout'))
                ->timeout((float) config('scanner.fetch.timeout'))
                ->withOptions([
                    'allow_redirects' => false,
                    'stream' => true,
                    'curl' => [CURLOPT_RESOLVE => [$target->curlResolveEntry()]],
                ])
                ->get($target->url);
        } catch (ConnectionException) {
            throw new FetchException(FetchFailure::Unreachable);
        }

        // Taille annoncée trop grande : inutile de télécharger.
        if ((int) $response->header('Content-Length') > $maxBytes) {
            throw new FetchException(FetchFailure::TooLarge, $response->status());
        }

        // Lecture par morceaux : on s'arrête dès que la limite est dépassée (en-tête absent ou mensonger).
        $stream = $response->toPsrResponse()->getBody();
        // Un flux déjà lu (réponse réutilisée) repartirait de la fin : on le rembobine si possible.
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $body = '';
        while (! $stream->eof()) {
            $body .= $stream->read(65536);
            if (strlen($body) > $maxBytes) {
                throw new FetchException(FetchFailure::TooLarge, $response->status());
            }
        }

        $location = $response->header('Location');

        return [
            'status' => $response->status(),
            'content_type' => strtolower($response->header('Content-Type')),
            'location' => $location !== '' ? $location : null,
            'body' => $body,
        ];
    }
}
