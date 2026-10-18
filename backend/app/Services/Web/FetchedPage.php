<?php

namespace App\Services\Web;

final readonly class FetchedPage
{
    public function __construct(
        public string $finalUrl,
        public int $status,
        public string $title,
        public string $text,
    ) {}
}
