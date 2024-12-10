<?php

namespace App\Domain\Strava\Activity\BuildLatestStravaActivities;

use App\Domain\Strava\Activity\ReadModel\ActivityDetailsRepository;
use App\Infrastructure\CQRS\Bus\Command;
use App\Infrastructure\CQRS\Bus\CommandHandler;
use League\Flysystem\FilesystemOperator;
use Twig\Environment;

final readonly class BuildLatestStravaActivitiesCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityDetailsRepository $activityDetailsRepository,
        private FilesystemOperator $filesystem,
        private Environment $twig,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildLatestStravaActivities);

        $this->filesystem->write(
            'build/strava-activities-latest.md',
            $this->twig->load('markdown/strava-activities.html.twig')->render([
                'activities' => $this->activityDetailsRepository->findAll(5),
                'addLinkToAllActivities' => true,
            ])
        );
    }
}
