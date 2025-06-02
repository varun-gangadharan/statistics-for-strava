<?php

namespace App\Tests\Domain\Strava\Gear\CustomGear\LinkCustomGearToActivities;

use App\Domain\Strava\Activity\ActivityId;
use App\Domain\Strava\Activity\ActivityWithRawData;
use App\Domain\Strava\Activity\ActivityWithRawDataRepository;
use App\Domain\Strava\Gear\CustomGear\CustomGearRepository;
use App\Domain\Strava\Gear\CustomGear\LinkCustomGearToActivities\LinkCustomGearToActivities;
use App\Domain\Strava\Gear\GearId;
use App\Domain\Strava\Gear\ImportedGear\ImportedGearRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Strava\Activity\ActivityBuilder;
use App\Tests\Domain\Strava\Gear\CustomGear\CustomGearBuilder;
use App\Tests\Domain\Strava\Gear\ImportedGear\ImportedGearBuilder;
use App\Tests\Domain\Strava\SpyStrava;
use App\Tests\SpyOutput;
use Spatie\Snapshots\MatchesSnapshots;

class LinkCustomGearToActivitiesCommandHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private CommandBus $commandBus;
    private SpyStrava $strava;

    public function testHandle(): void
    {
        $output = new SpyOutput();

        $this->getContainer()->get(ImportedGearRepository::class)->save(
            ImportedGearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('b12659861'))
                ->build()
        );

        $this->getContainer()->get(CustomGearRepository::class)->save(
            CustomGearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('custom'))
                ->build()
        );

        $this->getContainer()->get(CustomGearRepository::class)->save(
            CustomGearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('custom-two'))
                ->build()
        );

        $this->getContainer()->get(ActivityWithRawDataRepository::class)->add(
            ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('with-strava-gear'))
                    ->withGearId(GearId::fromUnprefixed('b12659861'))
                    ->build(), []
            )
        );

        $this->getContainer()->get(ActivityWithRawDataRepository::class)->add(
            ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('without-gear-but-not-tagged'))
                    ->withoutGearId()
                    ->build(), []
            )
        );

        $this->commandBus->dispatch(new LinkCustomGearToActivities($output));
        $this->assertMatchesTextSnapshot($output);

        $this->assertMatchesJsonSnapshot(
            $this->getConnection()->executeQuery('SELECT * FROM Activity')->fetchAllAssociative()
        );
        $this->assertMatchesJsonSnapshot(
            $this->getConnection()->executeQuery('SELECT * FROM Gear')->fetchAllAssociative()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
    }
}
