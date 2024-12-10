<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS\Deserializer;

use App\Infrastructure\CQRS\Bus\DomainCommand;

interface CommandDeserializer
{
    public function deserialize(string $serialized): DomainCommand;
}
