<?php

declare(strict_types=1);

namespace App\Domain\Strava\Gear\Maintenance;

use App\Infrastructure\ValueObject\String\NonEmptyStringLiteral;

final readonly class GearComponentId extends NonEmptyStringLiteral
{
    protected function validate(string $value): void
    {
        parent::validate($value);

        if (!preg_match('/^[a-z0-9\-]+$/', $value)) {
            throw new InvalidGearMaintenanceConfig(sprintf('Invalid component id "%s". Only lowercase letters, numbers and dashes are allowed.', $value));
        }
    }
}
