<?php

namespace App\Tests\Domain\Strava\Gear\Maintenance;

use App\Domain\Strava\Gear\Maintenance\GearComponentId;
use App\Domain\Strava\Gear\Maintenance\InvalidGearMaintenanceConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GearComponentIdTest extends TestCase
{
    #[DataProvider(methodName: 'provideGearComponentIds')]
    public function testGearComponentIdItShouldThrow(string $id): void
    {
        $this->expectExceptionObject(new InvalidGearMaintenanceConfig(
            'Invalid component id "'.$id.'". Only lowercase letters, numbers and dashes are allowed.'
        ));

        GearComponentId::fromString($id);
    }

    public static function provideGearComponentIds(): iterable
    {
        yield 'uppercase' => ['Uppercase'];
        yield 'special characters' => ['!@#$%^&*()'];
        yield 'spaces' => ['hashtag prefix'];
        yield 'multiple spaces' => ['hashtag  prefix'];
    }
}
