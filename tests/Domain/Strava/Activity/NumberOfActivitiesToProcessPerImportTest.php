<?php

namespace App\Tests\Domain\Strava\Activity;

use App\Domain\Strava\Activity\NumberOfActivitiesToProcessPerImport;
use PHPUnit\Framework\TestCase;

class NumberOfActivitiesToProcessPerImportTest extends TestCase
{
    public function testMaxNumberOfActivitiesProcessed(): void
    {
        $numberOfActivitiesToProcessPerImport = NumberOfActivitiesToProcessPerImport::fromInt(2);

        $this->assertFalse(
            $numberOfActivitiesToProcessPerImport->maxNumberProcessed()
        );
        $numberOfActivitiesToProcessPerImport->increaseNumberOfProcessedActivities();
        $this->assertFalse(
            $numberOfActivitiesToProcessPerImport->maxNumberProcessed()
        );
        $numberOfActivitiesToProcessPerImport->increaseNumberOfProcessedActivities();
        $this->assertTrue(
            $numberOfActivitiesToProcessPerImport->maxNumberProcessed()
        );
    }

    public function testItShouldThrow(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('NumberOfActivitiesToProcessPerImport must be greater than 0'));

        NumberOfActivitiesToProcessPerImport::fromInt(0);
    }
}
