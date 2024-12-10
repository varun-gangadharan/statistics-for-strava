<?php

namespace App\Tests\Infrastructure\ValueObject\Time;

use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\SerializableTimezone;
use PHPUnit\Framework\TestCase;

class SerializableDateTimeTest extends TestCase
{
    public function testFromString(): void
    {
        $this->assertEquals(
            new \DateTimeImmutable('2023-10-05 10:22:22', SerializableTimezone::default()),
            SerializableDateTime::fromString('2023-10-05 10:22:22', SerializableTimezone::default())
        );
    }

    public function testFromTimeStamp(): void
    {
        $this->assertEquals(
            new \DateTimeImmutable('2023-10-05 20:56:31', SerializableTimezone::default()),
            SerializableDateTime::fromTimestamp('1696532191', SerializableTimezone::default())
        );
    }

    public function testSerialize(): void
    {
        $date = SerializableDateTime::fromString('2023-10-05 10:22:22', SerializableTimezone::default());
        $this->assertEquals(
            Json::encode($date),
            Json::encode((string) $date),
        );
    }

    public function testToUtc(): void
    {
        $this->assertEquals(
            SerializableDateTime::fromString('2023-10-05 8:22:22', SerializableTimezone::UTC()),
            SerializableDateTime::fromString('2023-10-05 10:22:22', SerializableTimezone::default())->toUtc(),
        );
    }
}
