<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimiting;

use Symfony\Component\RateLimiter\RateLimiterFactory;

final class RateLimiterUsage
{
    /** @var RateLimiterFactory[] */
    private array $rateLimiters = [];

    public function addRateLimiter(string $key, RateLimiterFactory $rateLimiter): void
    {
        $this->rateLimiters[$key] = $rateLimiter;
    }

    public function getRateLimiter(string $key): ?RateLimiterFactory
    {
        return $this->rateLimiters[$key] ?? null;
    }
}
