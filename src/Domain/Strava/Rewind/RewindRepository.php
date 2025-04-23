<?php

declare(strict_types=1);

namespace App\Domain\Strava\Rewind;

use App\Domain\Strava\Activity\Activity;
use App\Infrastructure\ValueObject\Time\Year;

interface RewindRepository
{
    public function findLongestActivity(Year $year): Activity;

    public function countActivities(Year $year): int;
}
