<?php

declare(strict_types=1);

namespace App\Domain\Strava\Rewind;

use App\Domain\Strava\Activity\Activity;
use App\Domain\Strava\Activity\ActivityId;
use App\Domain\Strava\Activity\ActivityRepository;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\ValueObject\Time\Year;
use Doctrine\DBAL\Connection;

final readonly class DbalRewindRepository extends DbalRepository implements RewindRepository
{
    public function __construct(
        Connection $connection,
        private ActivityRepository $activityRepository,
    ) {
        parent::__construct($connection);
    }

    public function findLongestActivity(Year $year): Activity
    {
        $query = <<<SQL
            SELECT activityId
            FROM Activity
            WHERE strftime('%Y',startDateTime) = :year
            ORDER BY movingTimeInSeconds DESC
            LIMIT 1
        SQL;

        $activityId = $this->connection->executeQuery(
            $query,
            [
                'year' => (string) $year,
            ]
        )->fetchOne();

        return $this->activityRepository->find(ActivityId::fromString($activityId));
    }

    /**
     * @return array<string, int>
     */
    public function findPersonalRecordsPerMonth(Year $year): array
    {
        $query = <<<SQL
            SELECT  strftime('%Y-%m-01', startDateTime) AS date,
                    SUM(JSON_EXTRACT(data, '$.pr_count'))
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
