<?php

declare(strict_types=1);

namespace App\Domain\Strava\Gear\Maintenance;

use Symfony\Component\Yaml\Yaml;

final readonly class GearMaintenanceConfig
{
    private function __construct(
        private bool $isFeatureEnabled,
        private HashtagPrefix $hashtagPrefix,
        private GearComponents $gearComponents,
    ) {
    }

    public static function fromYmlString(
        ?string $ymlContent,
    ): self {
        if (is_null($ymlContent)) {
            return new self(
                isFeatureEnabled: false,
                hashtagPrefix: HashtagPrefix::fromString('dummy-'),
                gearComponents: GearComponents::empty(),
            );
        }
        $config = Yaml::parse($ymlContent);

        if (!array_key_exists('enabled', $config)) {
            throw new InvalidGearMaintenanceConfig('"enabled" property is required');
        }
        if (empty($config['hashtagPrefix'])) {
            throw new InvalidGearMaintenanceConfig('"hashtagPrefix" property is required');
        }
        if (empty($config['components'])) {
            throw new InvalidGearMaintenanceConfig('"components" property cannot be empty');
        }
        if (!is_array($config['components'])) {
            throw new InvalidGearMaintenanceConfig('"components" property must be an array');
        }

        return new self(
            isFeatureEnabled: $config['enabled'],
            hashtagPrefix: HashtagPrefix::fromString('dummy-'),
            gearComponents: GearComponents::empty(),
        );
    }

    public function getHashtagPrefix(): HashtagPrefix
    {
        return $this->hashtagPrefix;
    }

    public function getGearComponents(): GearComponents
    {
        return $this->gearComponents;
    }

    public function isFeatureEnabled(): bool
    {
        return $this->isFeatureEnabled;
    }
}
