<?php

namespace App\Tests\Domain\Strava\Gear\CustomGear;

use App\Domain\Strava\Gear\CustomGear\CustomGearConfig;
use App\Domain\Strava\Gear\CustomGear\InvalidCustomGearConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Yaml\Yaml;

class CustomGearConfigTest extends TestCase
{
    use MatchesSnapshots;

    public function testFromArrayWhenEmpty(): void
    {
        $this->assertFalse(CustomGearConfig::fromArray([])->isFeatureEnabled());
    }

    #[DataProvider(methodName: 'provideInvalidConfig')]
    public function testFromYmlStringItShouldThrow(array $yml, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidCustomGearConfig($expectedException));
        CustomGearConfig::fromArray($yml);
    }

    public static function provideInvalidConfig(): iterable
    {
        $yml = self::getValidYml();
        unset($yml['enabled']);
        yield 'missing "enabled" key' => [$yml, '"enabled" property is required'];

        $yml = self::getValidYml();
        unset($yml['hashtagPrefix']);
        yield 'missing "hashtagPrefix" key' => [$yml, '"hashtagPrefix" property is required'];

        $yml = self::getValidYml();
        unset($yml['customGears']);
        yield 'missing "customGears" key' => [$yml, '"customGears" property is required'];

        $yml = self::getValidYml();
        $yml['customGears'] = 'string';
        yield 'invalid "customGears" key' => [$yml, '"customGears" property must be an array'];
    }

    private static function getValidYml(): array
    {
        return Yaml::parse(<<<YML
enabled: true
hashtagPrefix: 'sfs'
customGears:
  - tag: 'gear-1'
    label: 'Custom Gear 1'
    isRetired: false
  - tag: 'gear-2'
    label: 'Custom Gear 2'
    isRetired: true
  - tag: 'gear-3'
    label: 'Custom Gear 3'
    isRetired: false
YML
        );
    }
}
