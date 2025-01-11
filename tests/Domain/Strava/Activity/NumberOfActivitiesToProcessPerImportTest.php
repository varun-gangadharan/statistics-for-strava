<?php

namespace App\Tests\Domain\Strava\Activity;

use App\Domain\Strava\Activity\NumberOfActivitiesToProcessPerImport;
use PHPUnit\Framework\TestCase;

class NumberOfActivitiesToProcessPerImportTest extends TestCase
{
    public function testGetValue(): void
    {
        $this->assertEquals(
            10,
            NumberOfActivitiesToProcessPerImport::fromInt(10)->getValue(),
        );
    }

    public function testItShouldThrow(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('NumberOfActivitiesToProcessPerImport must be greater than 0'));

        NumberOfActivitiesToProcessPerImport::fromInt(0);
    }
}
