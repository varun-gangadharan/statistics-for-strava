<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\Identifier;

use Ramsey\Uuid\Uuid as RamseyUuid;

class UuidFactory
{
    public static function random(): string
    {
        return RamseyUuid::uuid4()->toString();
    }
}
