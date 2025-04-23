<?php

namespace App\Tests\Domain\Strava\Rewind\FindLongestActivity;

use App\Domain\Strava\Activity\ActivityId;
use App\Domain\Strava\Activity\ActivityRepository;
use App\Domain\Strava\Activity\ActivityWithRawData;
use App\Domain\Strava\Activity\ActivityWithRawDataRepository;
use App\Domain\Strava\Gear\GearId;
use App\Domain\Strava\Rewind\FindLongestActivity\FindLongestActivity;
use App\Domain\Strava\Rewind\FindLongestActivity\FindLongestActivityQueryHandler;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Strava\Activity\ActivityBuilder;

class FindLongestActivityQueryHandlerTest extends ContainerTestCase
{
    private FindLongestActivityQueryHandler $queryHandler;

    public function testHandle(): void
    {
        $longestActivity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('0'))
            ->withMovingTimeInSeconds(10000)
            ->withStartDateTime(SerializableDateTime::fromString('2024-03-01 00:00:00'))
            ->build();

        $this->getContainer()->get(ActivityWithRawDataRepository::class)->add(ActivityWithRawData::fromState(
            $longestActivity,
            []
        ));

        $this->getContainer()->get(ActivityWithRawDataRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withMovingTimeInSeconds(20000)
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 00:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityWithRawDataRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId(GearId::fromUnprefixed('3'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-01-01 00:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityWithRawDataRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withGearId(GearId::fromUnprefixed('2'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-01 00:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityWithRawDataRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withGearId(GearId::fromUnprefixed('5'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-03 00:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityWithRawDataRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('8'))
                ->withGearId(GearId::fromUnprefixed('5'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-03 00:00:00'))
                ->build(),
            []
        ));

        /** @var \App\Domain\Strava\Rewind\FindLongestActivity\FindLongestActivityResponse $response */
        $response = $this->queryHandler->handle(new FindLongestActivity(Year::fromInt(2024)));

        $this->assertEquals(
            $longestActivity,
            $response->getLongestActivity(),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->queryHandler = new FindLongestActivityQueryHandler(
            $this->getConnection(),
            $this->getContainer()->get(ActivityRepository::class),
        );
    }
}
