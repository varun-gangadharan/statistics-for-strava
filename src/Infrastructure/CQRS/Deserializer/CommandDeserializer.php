<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS\Deserializer;

interface CommandDeserializer
{
    public function deserialize(string $serialized): DomainCommand;
}
