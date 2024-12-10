<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity\Stream;

use App\Domain\Strava\Activity\ActivityWasDeleted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class StreamActivityManager
{
    public function __construct(
        private ActivityStreamRepository $activityStreamRepository,
    ) {
    }

    #[AsEventListener]
    public function reactToActivityWasDeleted(ActivityWasDeleted $event): void
    {
        $segmentEfforts = $this->activityStreamRepository->findByActivityId($event->getActivityId());
        if ($segmentEfforts->isEmpty()) {
            return;
        }

        foreach ($segmentEfforts as $segmentEffort) {
            $this->activityStreamRepository->delete($segmentEffort);
        }
    }
}
