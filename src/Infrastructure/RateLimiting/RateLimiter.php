<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimiting;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class RateLimiter
{
    public function __construct(
        public string $configuration,
    ) {
    }
}
