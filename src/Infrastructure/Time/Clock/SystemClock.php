<?php

declare(strict_types=1);

namespace App\Infrastructure\Time\Clock;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\SerializableTimezone;

/**
 * @codeCoverageIgnore
 */
class SystemClock implements Clock
{
    public function getCurrentDateTimeImmutable(): SerializableDateTime
    {
        return SerializableDateTime::fromString('now', SerializableTimezone::UTC());
    }
}
