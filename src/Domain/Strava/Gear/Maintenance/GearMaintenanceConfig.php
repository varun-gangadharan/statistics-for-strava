<?php

declare(strict_types=1);

namespace App\Domain\Strava\Gear\Maintenance;

use App\Infrastructure\ValueObject\String\Name;
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

        $gearComponents = GearComponents::empty();
        foreach ($config['components'] as $component) {
            if (!array_key_exists('id', $component)) {
                throw new InvalidGearMaintenanceConfig('"id" property is required for each component');
            }
            if (!array_key_exists('label', $component)) {
                throw new InvalidGearMaintenanceConfig('"label" property is required for each component');
            }
            if (!array_key_exists('tag', $component)) {
                throw new InvalidGearMaintenanceConfig('"tag" property is required for each component');
            }
            if (empty($component['attachedTo'])) {
                throw new InvalidGearMaintenanceConfig('"attachedTo" property is required for each component');
            }
            if (!is_array($component['attachedTo'])) {
                throw new InvalidGearMaintenanceConfig('"attachedTo" property must be an array');
            }

            $gearComponents->add(GearComponent::create(
                id: GearComponentId::fromString($component['id']),
                label: Name::fromString($component['label']),
            ));
        }

        $componentIdCounts = array_count_values(array_column($config['components'], 'id'));
        if ($duplicates = array_keys(array_filter($componentIdCounts, fn ($count) => $count > 1))) {
            throw new InvalidGearMaintenanceConfig(sprintf('duplicate component IDs found: %s', implode(', ', $duplicates)));
        }

        return new self(
            isFeatureEnabled: $config['enabled'],
            hashtagPrefix: HashtagPrefix::fromString($config['hashtagPrefix']),
            gearComponents: $gearComponents,
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
