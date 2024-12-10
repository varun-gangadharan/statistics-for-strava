<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\Identifier;

use App\Infrastructure\ValueObject\String\NonEmptyStringLiteral;

abstract readonly class Identifier extends NonEmptyStringLiteral implements \JsonSerializable
{
    abstract public static function getPrefix(): string;

    protected function validate(string $value): void
    {
        parent::validate($value);

        if ('' !== static::getPrefix() && !str_starts_with($value, static::getPrefix())) {
            throw new \InvalidArgumentException('Identifier does not start with prefix "'.static::getPrefix().'", got: '.$value);
        }
    }

    public static function random(): static
    {
        return static::fromString(static::getPrefix().UuidFactory::random());
    }
}
