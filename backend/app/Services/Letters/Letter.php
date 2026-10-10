<?php

namespace App\Services\Letters;

final readonly class Letter
{
    public function __construct(
        public string $subject,
        public string $body,
    ) {}
}
