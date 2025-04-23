<?php

declare(strict_types=1);

namespace App\Domain\Strava\Rewind\FindLongestActivity;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\ValueObject\Time\Year;

/**
 * @implements Query<\App\Domain\Strava\Rewind\FindLongestActivity\FindLongestActivityResponse>
 */
final readonly class FindLongestActivity implements Query
{
    public function __construct(
        private Year $year,
    ) {
    }

    public function getYear(): Year
    {
        return $this->year;
    }
}
