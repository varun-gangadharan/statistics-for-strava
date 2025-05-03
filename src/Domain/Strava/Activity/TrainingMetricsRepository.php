<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

use App\Domain\Strava\Athlete\Athlete;
use App\Infrastructure\Repository\DbalRepository;

/**
 * Repository for storing and retrieving training metrics history.
 */
readonly class TrainingMetricsRepository extends DbalRepository
{
    /**
     * Ensure the athlete_training_metrics table exists.
     */
    public function createTableIfNotExists(): void
    {
        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS athlete_training_metrics (
                date TEXT PRIMARY KEY, 
                ctl REAL NOT NULL, 
                atl REAL NOT NULL, 
                tsb REAL NOT NULL, 
                ac_ratio REAL NOT NULL, 
                trimp REAL DEFAULT 0
            )
        SQL;

        // Execute the table creation statement
        $this->connection->executeStatement($sql);
    }

    /**
     * Get the latest metrics for an athlete before a given date.
     */
    public function getLatestMetricsBeforeDate(\DateTimeInterface $date): ?array
    {
        // Debug input parameter
        error_log('getLatestMetricsBeforeDate() called with date: '.$date->format('Y-m-d H:i:s'));

        $sql = <<<SQL
        SELECT 
            date, 
            ctl, 
            atl, 
            tsb, 
            ac_ratio as acRatio
        FROM athlete_training_metrics
        WHERE date <= :date
        ORDER BY date DESC
        LIMIT 1
    SQL;
        $params = [
            'date' => $date->format('Y-m-d'),
        ];

        // Debug SQL and params
        /*error_log('SQL: '.$sql);*/
        error_log('Params: '.json_encode($params));

        $result = $this->connection->fetchAssociative($sql, $params);

        // Debug result
        error_log('Result: '.($result ? json_encode($result) : 'null'));

        return $result ?: null;
    }

    /**
     * Get metrics for an athlete between two dates (inclusive).
     */
    public function getMetricsForDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $sql = <<<SQL
            SELECT 
                date, 
                ctl, 
                atl, 
                tsb, 
                ac_ratio as acRatio
            FROM athlete_training_metrics
            WHERE date BETWEEN :start_date AND :end_date
            ORDER BY date ASC
        SQL;

        $params = [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ];

        $results = $this->connection->fetchAllAssociative($sql, $params);

        // Convert to date-keyed array
        $metrics = [];
        foreach ($results as $row) {
            $metrics[$row['date']] = [
                'ctl' => (float) $row['ctl'],
                'atl' => (float) $row['atl'],
                'tsb' => (float) $row['tsb'],
                'acRatio' => (float) $row['acRatio'],
            ];
        }

        return $metrics;
    }

    /**
     * Store daily metrics for an athlete.
     */
    public function storeDailyMetrics(string $date, array $metrics): void
    {
        // Use ON CONFLICT for SQLite compatibility (or use database platform abstraction)
        $sql = <<<SQL
            INSERT INTO athlete_training_metrics (
                date, 
                ctl, 
                atl, 
                tsb, 
                ac_ratio,
                trimp
            ) VALUES (
                :date,
                :ctl,
                :atl,
                :tsb,
                :ac_ratio,
                :trimp
            )
            ON CONFLICT(date) DO UPDATE SET
                ctl = excluded.ctl,
                atl = excluded.atl,
                tsb = excluded.tsb,
                ac_ratio = excluded.ac_ratio,
                trimp = excluded.trimp
        SQL;

        $params = [
            'date' => $date,
            'ctl' => $metrics['ctl'],
            'atl' => $metrics['atl'],
            'tsb' => $metrics['tsb'],
            'ac_ratio' => $metrics['acRatio'],
            'trimp' => $metrics['trimp'] ?? 0,
        ];

        $this->connection->executeStatement($sql, $params);
    }

    /**
     * Store multiple days of metrics for an athlete.
     */
    public function storeMultipleDailyMetrics(array $dailyMetrics): void
    {
        foreach ($dailyMetrics as $date => $metrics) {
            $this->storeDailyMetrics($date, $metrics);
        }
    }
}
