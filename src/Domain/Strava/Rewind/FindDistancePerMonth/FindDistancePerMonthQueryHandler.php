<?php

declare(strict_types=1);

namespace App\Domain\Strava\Rewind\FindDistancePerMonth;

use App\Domain\Strava\Activity\SportType\SportType;
use App\Domain\Strava\Calendar\Month;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\ValueObject\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class FindDistancePerMonthQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindDistancePerMonth);

        $results = $this->connection->executeQuery(
            <<<SQL
                SELECT strftime('%m', startDateTime) AS month, sportType, SUM(distance) as distance
                FROM Activity
                WHERE strftime('%Y',startDateTime) = :year
                GROUP BY sportType, month
                ORDER BY month ASC, distance DESC 
            SQL,
            [
                'year' => (string) $query->getYear(),
            ]
        )->fetchAllAssociative();

        return new FindDistancePerMonthResponse(array_map(
            fn (array $result) => [
                Month::fromDate(SerializableDateTime::fromString(sprintf('%s-%s-01', $query->getYear(), $result['month']))),
                SportType::from($result['sportType']),
                Meter::from($result['distance'])->toKilometer(),
            ],
            $results,
        ));
    }
}
