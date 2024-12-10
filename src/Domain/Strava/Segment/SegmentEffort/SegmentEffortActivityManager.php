<?php

declare(strict_types=1);

namespace App\Domain\Strava\Segment\SegmentEffort;

use App\Domain\Strava\Activity\ActivityWasDeleted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class SegmentEffortActivityManager
{
    public function __construct(
        private SegmentEffortRepository $segmentEffortRepository,
    ) {
    }

    #[AsEventListener]
    public function reactToActivityWasDeleted(ActivityWasDeleted $event): void
    {
        $segmentEfforts = $this->segmentEffortRepository->findByActivityId($event->getActivityId());
        if ($segmentEfforts->isEmpty()) {
            return;
        }

        foreach ($segmentEfforts as $segmentEffort) {
            $this->segmentEffortRepository->delete($segmentEffort);
        }
    }
}
