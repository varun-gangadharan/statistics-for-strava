<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity\BuildDaytimeStatsChart;

use App\Domain\Strava\Activity\ReadModel\ActivityDetailsRepository;
use App\Infrastructure\CQRS\Bus\Command;
use App\Infrastructure\CQRS\Bus\CommandHandler;
use App\Infrastructure\Serialization\Json;
use League\Flysystem\FilesystemOperator;

final readonly class BuildDaytimeStatsChartCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityDetailsRepository $activityDetailsRepository,
        private FilesystemOperator $filesystem,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildDaytimeStatsChart);

        $this->filesystem->write(
            'build/charts/chart-daytime-stats.json',
            Json::encode(
                [
                    'width' => 1000,
                    'height' => 300,
                    'options' => DaytimeStatsChartsBuilder::fromDaytimeStats(
                        DaytimeStats::fromActivities($this->activityDetailsRepository->findAll()),
                    )->build(),
                ],
                JSON_PRETTY_PRINT
            ),
        );
    }
}
