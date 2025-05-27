<?php

declare(strict_types=1);

namespace App\Domain\Strava\Gear\CustomGear;

use App\Domain\Strava\Gear\Gear;
use App\Infrastructure\ValueObject\String\Tag;

final readonly class CustomGear
{
    private function __construct(
        private Gear $gear,
        private Tag $customGearTag,
    ) {
    }

    public static function create(
        Gear $gear,
        Tag $customGearTag,
    ): self {
        return new self(
            gear: $gear,
            customGearTag: $customGearTag,
        );
    }

    public function getGear(): Gear
    {
        return $this->gear;
    }

    public function getCustomGearTag(): Tag
    {
        return $this->customGearTag;
    }
}
