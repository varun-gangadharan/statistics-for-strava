<?php

declare(strict_types=1);

namespace App\Domain\Strava\Rewind;

use App\Domain\Strava\Activity\Activity;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;

interface RewindRepository
{
    public function findAvailableRewindYears(SerializableDateTime $now): Years;

    /**
     * @return array<string, int>
     */
    public function findMovingTimePerByDay(Year $year): array;

    /**
     * @return array<string, int>
     */
    public function findMovingTimePerGear(Year $year): array;

    public function findLongestActivity(Year $year): Activity;

    /**
     * @return array<string, int>
     */
    public function findPersonalRecordsPerMonth(Year $year): array;

    public function countActivities(Year $year): int;
}
