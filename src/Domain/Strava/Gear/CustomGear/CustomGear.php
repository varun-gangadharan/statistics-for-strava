<?php

declare(strict_types=1);

namespace App\Domain\Strava\Gear\CustomGear;

use App\Domain\Strava\Gear\Gear;
use App\Domain\Strava\Gear\GearId;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\String\Tag;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class CustomGear implements Gear
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

    public function getId(): GearId
    {
        return $this->gear->getId();
    }

    public function updateName(string $name): Gear
    {
        return $this->gear->updateName($name);
    }

    public function getOriginalName(): string
    {
        return $this->gear->getOriginalName();
    }

    public function getName(): string
    {
        return $this->gear->getName();
    }

    public function getSanitizedName(): string
    {
        return $this->gear->getSanitizedName();
    }

    public function getDistance(): Kilometer
    {
        return $this->gear->getDistance();
    }

    public function isRetired(): bool
    {
        return $this->gear->isRetired();
    }

    public function updateIsRetired(bool $isRetired): Gear
    {
        return $this->gear->updateIsRetired($isRetired);
    }

    public function updateDistance(Meter $distance): Gear
    {
        return $this->gear->updateDistance($distance);
    }

    public function getCreatedOn(): SerializableDateTime
    {
        return $this->gear->getCreatedOn();
    }

    public function getImageSrc(): ?string
    {
        return $this->gear->getImageSrc();
    }

    public function enrichWithImageSrc(string $imageSrc): Gear
    {
        return $this->gear->enrichWithImageSrc($imageSrc);
    }

    public function getCustomGearTag(): Tag
    {
        return $this->customGearTag;
    }
}
