<?php

namespace App\Tests\Infrastructure\Time\Clock;

use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

class PausedClock implements Clock
{
    private SerializableDateTime $pausedOn;

    private function __construct(SerializableDateTime $pausedOn)
    {
        $this->pausedOn = $pausedOn;
    }

    public static function on(SerializableDateTime $on): PausedClock
    {
        return new self($on);
    }

    public function getCurrentDateTimeImmutable(): SerializableDateTime
    {
        return $this->pausedOn;
    }
}
