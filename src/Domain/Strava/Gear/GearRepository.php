<?php

namespace App\Domain\Strava\Gear;

interface GearRepository
{
    public function add(Gear $gear): void;

    public function update(Gear $gear): void;

    public function findAll(): Gears;

    public function find(GearId $gearId): Gear;
}
