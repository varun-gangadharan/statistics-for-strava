<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\Math;

final readonly class Percentage
{
    private function __construct(private int $value)
    {
        if ($value < 0 || $value > 100) {
            throw new \InvalidArgumentException('Percentage must be between 0 and 100');
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function toInt(): int
    {
        return $this->value;
    }
}
