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
}
