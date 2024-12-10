<?php

namespace App\Infrastructure\ValueObject;

final readonly class Weight
{
    private function __construct(
        private float $weight,
    ) {
    }

    public static function fromKilograms(float $weight): self
    {
        return new self($weight);
    }

    public function getFloat(): float
    {
        return $this->weight;
    }
}
