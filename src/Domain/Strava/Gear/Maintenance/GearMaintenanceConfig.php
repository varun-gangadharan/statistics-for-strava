<?php

declare(strict_types=1);

namespace App\Domain\Strava\Gear\Maintenance;

use App\Domain\Strava\Gear\GearId;
use App\Domain\Strava\Gear\GearIds;
use App\Infrastructure\ValueObject\String\Name;
use Symfony\Component\Yaml\Yaml;

final readonly class GearMaintenanceConfig
{
    private GearComponents $gearComponents;

    private function __construct(
        private bool $isFeatureEnabled,
        private HashtagPrefix $hashtagPrefix,
    ) {
        $this->gearComponents = GearComponents::empty();
    }

    public static function fromYmlString(
        ?string $ymlContent,
    ): self {
        if (is_null($ymlContent)) {
            return new self(
                isFeatureEnabled: false,
                hashtagPrefix: HashtagPrefix::fromString('dummy-'),
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

        $gearMaintenanceConfig = new self(
            isFeatureEnabled: $config['enabled'],
            hashtagPrefix: HashtagPrefix::fromString($config['hashtagPrefix']),
        );

        foreach ($config['components'] as $component) {
            if (!array_key_exists('tag', $component)) {
                throw new InvalidGearMaintenanceConfig('"tag" property is required for each component');
            }
            if (!array_key_exists('label', $component)) {
                throw new InvalidGearMaintenanceConfig('"label" property is required for each component');
            }
            if (empty($component['attachedTo'])) {
                throw new InvalidGearMaintenanceConfig('"attachedTo" property is required for each component');
            }
            if (!is_array($component['attachedTo'])) {
                throw new InvalidGearMaintenanceConfig('"attachedTo" property must be an array');
            }
            if (empty($component['maintenance'])) {
                throw new InvalidGearMaintenanceConfig('You need at least one maintenance task for each component');
            }
            if (!is_array($component['maintenance'])) {
                throw new InvalidGearMaintenanceConfig('"maintenance" property must be an array');
            }

            $gearComponent = GearComponent::create(
                tag: Tag::fromString($component['tag']),
                label: Name::fromString($component['label']),
                attachedTo: GearIds::fromArray(array_map(
                    fn (string $gearId) => GearId::fromString($gearId),
                    $component['attachedTo']
                )),
            );

            foreach ($component['maintenance'] as $task) {
                if (empty($task['tag'])) {
                    throw new InvalidGearMaintenanceConfig('"tag" property is required for each maintenance task');
                }
                if (empty($task['label'])) {
                    throw new InvalidGearMaintenanceConfig('"label" property is required for each maintenance task');
                }
                if (empty($task['interval'])) {
                    throw new InvalidGearMaintenanceConfig('"interval" property is required for each maintenance task');
                }
                if (empty($task['interval']['value']) || empty($task['interval']['unit'])) {
                    throw new InvalidGearMaintenanceConfig('"interval" property must have "value" and "unit" properties');
                }

                if (!$intervalUnit = IntervalUnit::tryFrom($task['interval']['unit'])) {
                    throw new InvalidGearMaintenanceConfig(sprintf('invalid interval unit "%s"', $task['interval']['unit']));
                }

                $gearComponent->addMaintenanceTask(MaintenanceTask::create(
                    tag: Tag::fromString($task['tag']),
                    label: Name::fromString($task['label']),
                    intervalValue: $task['interval']['value'],
                    intervalUnit: $intervalUnit
                ));
            }

            $maintenanceTags = array_count_values(array_column($component['maintenance'], 'tag'));
            if ($duplicates = array_keys(array_filter($maintenanceTags, fn (int $count) => $count > 1))) {
                throw new InvalidGearMaintenanceConfig(sprintf('duplicate maintenance tags found for component "%s:" %s', $gearComponent->getLabel(), implode(', ', $duplicates)));
            }

            $gearMaintenanceConfig->addComponent($gearComponent);
        }

        $componentTags = array_count_values(array_column($config['components'], 'tag'));
        if ($duplicates = array_keys(array_filter($componentTags, fn (int $count) => $count > 1))) {
            throw new InvalidGearMaintenanceConfig(sprintf('duplicate component tags found: %s', implode(', ', $duplicates)));
        }

        return $gearMaintenanceConfig;
    }

    private function addComponent(GearComponent $component): void
    {
        $this->gearComponents->add($component);
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
