<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

final readonly class NumberOfActivitiesToProcessPerImport
{
    private function __construct(
        private int $value,
    ) {
        if ($this->value <= 0) {
            throw new \InvalidArgumentException('NumberOfActivitiesToProcessPerImport must be greater than 0');
        }
    }

    public static function fromInt(int $value): NumberOfActivitiesToProcessPerImport
    {
        return new self($value);
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
