<?php

namespace App\Domain\Strava\Activity\BuildWeekdayStatsChart;

use App\Domain\Strava\Activity\ReadModel\ActivityDetailsRepository;
use App\Infrastructure\CQRS\Bus\Command;
use App\Infrastructure\CQRS\Bus\CommandHandler;
use App\Infrastructure\Serialization\Json;
use League\Flysystem\FilesystemOperator;

final readonly class BuildWeekdayStatsChartCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityDetailsRepository $activityDetailsRepository,
        private FilesystemOperator $filesystem,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildWeekdayStatsChart);

        $this->filesystem->write(
            'build/charts/chart-weekday-stats_1000_300.json',
            Json::encode(
                [
                    'width' => 1000,
                    'height' => 300,
                    'options' => WeekdayStatsChartsBuilder::fromWeekdayStats(
                        WeekdayStats::fromActivities($this->activityDetailsRepository->findAll()),
                    )->build(),
                ],
                JSON_PRETTY_PRINT
            ),
        );
    }
}
