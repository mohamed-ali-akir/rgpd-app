<?php

namespace App\Services\Scanner\Sources;

use App\Enums\FindingSource;
use App\Services\Identity\Identity;
use App\Services\Identity\IdentityMatch;
use App\Services\Identity\IdentityMatcher;
use App\Services\Scanner\Candidate;
use App\Services\Search\SearchProvider;
use App\Services\Search\SearchResult;
use App\Services\Search\SearchUnavailableException;
use App\Services\Web\FetchException;
use App\Services\Web\PageFetcher;
use App\Services\Web\UrlNormalizer;

/**
 * Recherche web : on cherche le nom, l'e-mail et le téléphone de l'utilisateur, puis on relit chaque page
 * trouvée (dans la limite de robots.txt) pour confirmer que ses données y figurent vraiment.
 *
 * Budget : nombre de pages relues et durée totale limités, pour qu'un scan reste raisonnable
 * même si des sites sont lents.
 */
class WebSearchSource implements ScanSource
{
    public function __construct(
        private readonly SearchProvider $search,
        private readonly PageFetcher $fetcher,
        private readonly IdentityMatcher $matcher,
        private readonly UrlNormalizer $normalizer,
    ) {}

    public function key(): FindingSource
    {
        return FindingSource::WebSearch;
    }

    public function scan(Identity $identity): array
    {
        if (! $this->search->isAvailable()) {
            throw new SourceSkippedException('Aucun moteur de recherche configuré.');
        }

        $results = $this->collectResults($identity);

        $config = config('scanner.scan');
        $deadline = microtime(true) + (int) $config['time_budget_seconds'];
        $pagesLeft = (int) $config['max_pages_fetched'];
        $candidates = [];

        foreach ($results as $result) {
            $canFetch = $pagesLeft > 0 && microtime(true) < $deadline;
            $candidate = $canFetch
                ? $this->confirmByReadingPage($identity, $result)
                : $this->fromSnippet($identity, $result, 'budget');
            if ($canFetch) {
                $pagesLeft--;
            }

            if ($candidate !== null && $candidate->confidence >= (int) $config['min_confidence']) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    /**
     * Toutes les requêtes, résultats dédoublonnés (une même page peut sortir pour le nom et pour l'e-mail).
     * Les pages de l'application elle-même sont ignorées.
     *
     * @return list<SearchResult>
     */
    private function collectResults(Identity $identity): array
    {
        $queries = ['"'.$identity->fullName().'"'];
        foreach ($identity->emails as $email) {
            $queries[] = '"'.$email.'"';
        }
        if ($identity->phone !== null && trim($identity->phone) !== '') {
            $queries[] = '"'.trim($identity->phone).'"';
        }

        $ownDomain = $this->normalizer->domain((string) config('app.url'));
        $limit = (int) config('scanner.search.results_per_query');
        $seen = [];
        $results = [];
        $failures = 0;

        foreach ($queries as $query) {
            try {
                $found = $this->search->search($query, $limit);
            } catch (SearchUnavailableException $e) {
                // Une requête en échec n'annule pas les autres ; si toutes échouent, la source est en panne.
                $failures++;

                continue;
            }

            foreach ($found as $result) {
                $hash = $this->normalizer->hash($result->url);
                if (isset($seen[$hash]) || $this->normalizer->domain($result->url) === $ownDomain) {
                    continue;
                }
                $seen[$hash] = true;
                $results[] = $result;
            }
        }

        if ($failures === count($queries)) {
            throw new SearchUnavailableException('Le moteur de recherche n\'a répondu à aucune requête.');
        }

        return $results;
    }

    private function confirmByReadingPage(Identity $identity, SearchResult $result): ?Candidate
    {
        try {
            $page = $this->fetcher->fetch($result->url);
        } catch (FetchException $e) {
            // Page illisible (robots.txt, erreur…) : on se contente de l'extrait du moteur, avec moins de confiance.
            return $this->fromSnippet($identity, $result, $e->failure->value);
        }

        $match = $this->matcher->match($identity, $page->title.' '.$page->text);
        if (! $match->found()) {
            return null; // la page ne parle pas de l'utilisateur (le moteur s'est trompé ou la page a changé)
        }

        return $this->candidate($result, $page->title !== '' ? $page->title : $result->title, $match, $match->confidence, [
            'verified' => true,
        ]);
    }

    private function fromSnippet(Identity $identity, SearchResult $result, string $reason): ?Candidate
    {
        $match = $this->matcher->match($identity, $result->title.' '.$result->snippet);
        if (! $match->found()) {
            return null;
        }

        $confidence = (int) round($match->confidence * (float) config('scanner.scan.unverified_confidence_factor'));

        return $this->candidate($result, $result->title, $match, $confidence, ['verified' => false, 'unverified_reason' => $reason]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function candidate(SearchResult $result, string $title, IdentityMatch $match, int $confidence, array $meta): Candidate
    {
        return new Candidate(
            source: FindingSource::WebSearch,
            url: $result->url,
            title: mb_substr($title, 0, 300),
            snippet: mb_substr($result->snippet, 0, 500),
            dataTypes: $match->dataTypes,
            confidence: $confidence,
            meta: $meta,
        );
    }
}
