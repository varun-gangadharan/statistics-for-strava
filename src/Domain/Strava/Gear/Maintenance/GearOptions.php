<?php

declare(strict_types=1);

namespace App\Domain\Strava\Gear\Maintenance;

use App\Domain\Strava\Gear\GearId;

final class GearOptions
{
    /** @var array<int, array{0: GearId, 1: string}> */
    private array $options = [];

    private function __construct(
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    public function add(GearId $gearId, string $imgSrc): void
    {
        $this->options[] = [$gearId, $imgSrc];
    }

    /**
     * @return array<int, array{0: GearId, 1: string}>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
