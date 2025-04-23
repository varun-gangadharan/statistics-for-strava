<?php

declare(strict_types=1);

namespace App\Domain\Strava\Rewind\FindLongestActivity;

use App\Domain\Strava\Activity\Activity;
use App\Infrastructure\CQRS\Query\Response;

final readonly class FindLongestActivityResponse implements Response
{
    public function __construct(
        private Activity $longestActivity,
    ) {
    }

    public function getLongestActivity(): Activity
    {
        return $this->longestActivity;
    }
}
