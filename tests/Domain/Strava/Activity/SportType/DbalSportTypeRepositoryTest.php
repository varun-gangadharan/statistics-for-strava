<?php

namespace App\Tests\Domain\Strava\Activity\SportType;

use App\Domain\Strava\Activity\ActivityId;
use App\Domain\Strava\Activity\ActivityRepository;
use App\Domain\Strava\Activity\SportType\DbalSportTypeRepository;
use App\Domain\Strava\Activity\SportType\SportType;
use App\Domain\Strava\Activity\SportType\SportTypes;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Strava\Activity\ActivityBuilder;

class DbalSportTypeRepositoryTest extends ContainerTestCase
{
    public function testFindAll(): void
    {
        $sportTypeRepository = new DbalSportTypeRepository(
            $this->getConnection(),
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
            SportTypes::fromArray([SportType::RUN, SportType::WALK]),
            $sportTypeRepository->findAll(),
        );
    }
}
