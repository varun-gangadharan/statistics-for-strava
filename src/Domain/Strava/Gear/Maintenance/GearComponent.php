<?php

declare(strict_types=1);

namespace App\Domain\Strava\Gear\Maintenance;

use App\Infrastructure\ValueObject\String\Name;

final readonly class GearComponent
{
    public static function create(
        GearComponentId $id,
        Name $label,
    ): self {
    }
}
