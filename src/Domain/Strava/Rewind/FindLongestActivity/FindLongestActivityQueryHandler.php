<?php

declare(strict_types=1);

namespace App\Domain\Strava\Rewind\FindLongestActivity;

use App\Domain\Strava\Activity\ActivityId;
use App\Domain\Strava\Activity\ActivityRepository;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use Doctrine\DBAL\Connection;

final readonly class FindLongestActivityQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
        private ActivityRepository $activityRepository,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindLongestActivity);

        $activityId = $this->connection->executeQuery(
            <<<SQL
                SELECT activityId
                FROM Activity
                WHERE strftime('%Y',startDateTime) = :year
                ORDER BY movingTimeInSeconds DESC
                LIMIT 1
            SQL,
            [
                'year' => (string) $query->getYear(),
            ]
        )->fetchOne();

        return new FindLongestActivityResponse($this->activityRepository->find(ActivityId::fromString($activityId)));
    }
}
