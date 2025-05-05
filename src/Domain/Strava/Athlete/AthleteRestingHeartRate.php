<?php
declare(strict_types=1);

namespace App\Domain\Strava\Athlete;

/**
 * Value object representing an athlete's resting heart rate.
 */
final readonly class AthleteRestingHeartRate
{
    private int $value;

    private function __construct(int $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a new instance from an integer value.
     */
    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    /**
     * Returns the resting heart rate.
     */
    public function toInt(): int
    {
        return $this->value;
    }

    /**
     * String representation of the resting heart rate.
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }
}