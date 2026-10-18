<?php

namespace App\Services\Search;

final readonly class SearchResult
{
    public function __construct(
        public string $url,
        public string $title,
        public string $snippet,
    ) {}
}
