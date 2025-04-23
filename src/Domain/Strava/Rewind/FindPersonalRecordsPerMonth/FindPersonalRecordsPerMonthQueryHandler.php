<?php

declare(strict_types=1);

namespace App\Domain\Strava\Rewind\FindPersonalRecordsPerMonth;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use Doctrine\DBAL\Connection;

final readonly class FindPersonalRecordsPerMonthQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindPersonalRecordsPerMonth);

        return new FindPersonalRecordsPerMonthResponse($this->connection->executeQuery(
            <<<SQL
                SELECT  strftime('%Y-%m-01', startDateTime) AS date,
                        SUM(JSON_EXTRACT(data, '$.pr_count'))
                FROM Activity
                WHERE strftime('%Y',startDateTime) = :year
                GROUP BY date
                ORDER BY date DESC
            SQL,
            [
                'year' => (string) $query->getYear(),
            ]
        )->fetchAllKeyValue());
    }
}
