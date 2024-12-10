<?php

namespace App\Tests\Infrastructure\ValueObject\Time;

use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\SerializableTimezone;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    public function testItShouldExposeFromAndTillDates(): void
    {
        $from = SerializableDateTime::fromString('25-02-1982', SerializableTimezone::UTC());
        $till = SerializableDateTime::fromString('30-03-1982', SerializableTimezone::UTC());
        $dateRange = DateRange::fromDates($from, $till);
        $this->assertEquals($from, $dateRange->getFrom());
        $this->assertEquals($till, $dateRange->getTill());
    }

    public function testItShouldThrowWhenTillComesBeforeFrom(): void
    {
        $from = SerializableDateTime::fromString('25-02-1982', SerializableTimezone::UTC());
        $till = SerializableDateTime::fromString('30-03-1982', SerializableTimezone::UTC());
        $this->expectExceptionObject(new \InvalidArgumentException('invalid DateRange: 1982-03-30 00:00:00 till 1982-02-25 00:00:00'));

        DateRange::fromDates($till, $from);
    }
}
