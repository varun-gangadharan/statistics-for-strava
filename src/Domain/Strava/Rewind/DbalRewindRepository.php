<?php

declare(strict_types=1);

namespace App\Domain\Strava\Rewind;

use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;

final readonly class DbalRewindRepository extends DbalRepository implements RewindRepository
{
    public function findAvailableRewindYears(SerializableDateTime $now): Years
    {
        $currentYear = $now->getYear();
        if (RewindCutOffDate::fromYear(Year::fromInt($currentYear))->isBefore($now)) {
            $currentYear = 0;
        }

        $years = $this->connection->executeQuery(
            'SELECT DISTINCT strftime("%Y",startDateTime) AS year FROM Activity
             WHERE year != :currentYear 
             ORDER BY year DESC',
            [
                'currentYear' => $currentYear,
            ]
        )->fetchFirstColumn();

        return Years::fromArray(array_map(
            static fn (int $year): Year => Year::fromInt((int) $year),
            $years
        ));
    }

    /**
     * @return array<string, int>
     */
    public function findMovingLevelGroupedByDay(Year $year): array
    {
        $query = <<<SQL
            SELECT
                strftime('%Y-%m-%d', startDateTime) AS date,
                MAX(MIN(CAST(Activity.movingTimeInSeconds / 1000 AS INTEGER), 4), 1) AS level
            FROM Activity
            WHERE strftime('%Y',startDateTime) = :year
            GROUP BY date
            ORDER BY date DESC
        SQL;

        return $this->connection->executeQuery(
            $query,
            [
                'year' => (string) $year,
            ]
        )->fetchAllKeyValue();
    }

    /**
     * @return array<string, int>
     */
    public function findMovingTimePerGear(Year $year): array
    {
        $query = <<<SQL
            SELECT gearId, SUM(movingTimeInSeconds) as movingTimeInSeconds
            FROM Activity
            WHERE strftime('%Y',startDateTime) = :year
            AND gearId IS NOT NULL
            GROUP BY gearId
        SQL;

        return $this->connection->executeQuery(
            $query,
            [
                'year' => (string) $year,
            ]
        )->fetchAllKeyValue();
    }

    public function countActivities(Year $year): int
    {
        $query = <<<SQL
            SELECT COUNT(*)
            FROM Activity
            WHERE strftime('%Y',startDateTime) = :year
        SQL;

        return (int) $this->connection->executeQuery(
            $query,
            [
                'year' => (string) $year,
            ]
        )->fetchOne();
    }
}
