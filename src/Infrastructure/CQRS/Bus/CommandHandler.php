<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS\Bus;

interface CommandHandler
{
    public function handle(Command $command): void;
}
