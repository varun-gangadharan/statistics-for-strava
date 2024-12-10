<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity\BuildYearlyDistanceChart;

use App\Domain\Strava\Activity\ReadModel\ActivityDetailsRepository;
use App\Infrastructure\CQRS\Bus\Command;
use App\Infrastructure\CQRS\Bus\CommandHandler;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use League\Flysystem\FilesystemOperator;

final readonly class BuildYearlyDistanceChartCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityDetailsRepository $activityDetailsRepository,
        private FilesystemOperator $filesystem,
        private Clock $clock,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildYearlyDistanceChart);

        $this->filesystem->write(
            'build/charts/chart-yearly-distance-stats.json',
            Json::encode(
                [
                    'width' => 1000,
                    'height' => 400,
                    'options' => YearlyDistanceChartBuilder::fromActivities(
                        activities: $this->activityDetailsRepository->findAll(),
                        now: SerializableDateTime::fromDateTimeImmutable($this->clock->now()),
                    )->build(),
                ],
                JSON_PRETTY_PRINT
            ),
        );
    }
}
