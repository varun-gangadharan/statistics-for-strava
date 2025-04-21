<?php

declare(strict_types=1);

namespace App\Domain\Strava\Rewind;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;

interface RewindRepository
{
    public function findAvailableRewindYears(SerializableDateTime $now): Years;

    /**
     * @return array<string, int>
     */
    public function findMovingLevelGroupedByDay(Year $year): array;

    public function countActivities(Year $year): int;
}
