<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\Time;

class SerializableDateTime extends \DateTimeImmutable implements \JsonSerializable, \Stringable
{
    private function __construct(
        string $string,
        SerializableTimezone $timezone,
    ) {
        parent::__construct($string, $timezone);
    }

    public static function fromDateTimeImmutable(\DateTimeImmutable $date): self
    {
        return self::fromString($date->format('Y-m-d H:i:s'), SerializableTimezone::fromString($date->getTimezone()->getName()));
    }

    public static function fromString(string $string, SerializableTimezone $timezone): self
    {
        return new self($string, $timezone);
    }

    public static function fromTimestamp(int $unixTimestamp, SerializableTimezone $timezone): self
    {
        return self::fromString('now', $timezone)->setTimestamp($unixTimestamp);
    }

    public function __toString(): string
    {
        return $this->iso();
    }

    public function jsonSerialize(): string
    {
        return $this->iso();
    }

    public function iso(): string
    {
        return $this->format('Y-m-d H:i:s');
    }

    public function toUtc(): self
    {
        return $this->toTimezone(SerializableTimezone::UTC());
    }

    public function toTimezone(SerializableTimezone $timezone): self
    {
        return $this->setTimezone($timezone);
    }

    public function getHourWithoutLeadingZero(): int
    {
        return (int) $this->format('G');
    }

    public function getMinutesWithoutLeadingZero(): int
    {
        return intval($this->format('i'));
    }

    public function getMonthWithoutLeadingZero(): int
    {
        return intval($this->format('n'));
    }

    public function getYear(): int
    {
        return (int) $this->format('Y');
    }

    public function getWeekNumber(): int
    {
        return (int) $this->format('W');
    }

    /**
     * @return int[]
     */
    public function getYearAndWeekNumber(): array
    {
        return [$this->getYear(), $this->getWeekNumber()];
    }

    public function getYearAndWeekNumberString(): string
    {
        return implode('-', $this->getYearAndWeekNumber());
    }

    public function isAfterOrOn(SerializableDateTime $that): bool
    {
        return $this >= $that;
    }

    public function isBeforeOrOn(SerializableDateTime $that): bool
    {
        return $this <= $that;
    }

    public function isBefore(SerializableDateTime $that): bool
    {
        return !$this->isAfterOrOn($that);
    }

    public function isAfter(SerializableDateTime $that): bool
    {
        return !$this->isBeforeOrOn($that);
    }
}
