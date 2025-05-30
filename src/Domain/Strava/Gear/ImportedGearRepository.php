<?php

namespace App\Domain\Strava\Gear;

interface ImportedGearRepository
{
    public function save(ImportedGear $gear): void;

    public function findAll(): Gears;

    public function find(GearId $gearId): ImportedGear;
}
