<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity\SportType;

use Doctrine\DBAL\Connection;

final readonly class DbalSportTypeRepository implements SportTypeRepository
{
    public function __construct(
        private Connection $connection,
        private SportTypesToImport $sportTypesToImport,
    ) {
    }

    public function findAll(): SportTypes
    {
        $orderByStatement = [];
        foreach ($this->sportTypesToImport as $index => $sportType) {
            $orderByStatement[] = sprintf('WHEN "%s" THEN %d', $sportType->value, $index);
        }
        $orderByStatement[] = 'ELSE 9999';

        return SportTypes::fromArray(array_map(
            fn (string $sportType) => SportType::from($sportType),
            $this->connection->executeQuery(
                sprintf('SELECT DISTINCT sportType FROM Activity ORDER BY CASE sportType %s END', implode(' ', $orderByStatement))
            )->fetchFirstColumn()
        ));
    }
}
