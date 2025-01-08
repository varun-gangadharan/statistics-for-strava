<?php

namespace App\Tests\Domain\Strava\Activity;

use App\Domain\Strava\Activity\ActivityId;
use App\Domain\Strava\Activity\ActivityRepository;
use App\Domain\Strava\Activity\ActivityType;
use App\Domain\Strava\Activity\ActivityTypes;
use App\Domain\Strava\Activity\SportType\SportType;
use App\Domain\Strava\Activity\SportType\SportTypeRepository;
use App\Domain\Strava\Activity\SportTypeBasedActivityTypeRepository;
use App\Tests\ContainerTestCase;

class SportTypeBasedActivityTypeRepositoryTest extends ContainerTestCase
{
    public function testFindAll(): void
    {
        $activityTypeRepository = new SportTypeBasedActivityTypeRepository(
            $this->getContainer()->get(SportTypeRepository::class),
        );

        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::random())
                ->withSportType(SportType::WALK)
                ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::random())
                ->withSportType(SportType::RUN)
                ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::random())
                ->withSportType(SportType::RUN)
                ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::random())
                ->withSportType(SportType::WALK)
                ->build()
        );

        $this->assertEquals(
            ActivityTypes::fromArray([ActivityType::RUN, ActivityType::WALK]),
            $activityTypeRepository->findAll(),
        );
    }
}
