<?php

namespace App\Domain\Strava\Activity\BuildWeeklyDistanceChart;

use App\Domain\Strava\Activity\ReadModel\ActivityDetailsRepository;
use App\Infrastructure\CQRS\Bus\Command;
use App\Infrastructure\CQRS\Bus\CommandHandler;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Lcobucci\Clock\Clock;
use League\Flysystem\FilesystemOperator;

final readonly class BuildWeeklyDistanceChartCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityDetailsRepository $activityDetailsRepository,
        private FilesystemOperator $filesystem,
        private Clock $clock,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildWeeklyDistanceChart);

        $this->filesystem->write(
            'build/charts/chart_1000_300.json',
            Json::encode(
                [
                    'width' => 1000,
                    'height' => 300,
                    'options' => WeeklyDistanceChartBuilder::fromActivities(
                        activities: $this->activityDetailsRepository->findAll(),
                        now: SerializableDateTime::fromDateTimeImmutable($this->clock->now()),
                    )->build(),
                ],
                JSON_PRETTY_PRINT
            ),
        );
    }
}
