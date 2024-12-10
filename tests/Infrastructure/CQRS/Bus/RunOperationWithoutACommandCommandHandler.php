<?php

namespace App\Tests\Infrastructure\CQRS\Bus;

use App\Infrastructure\CQRS\Bus\Command;
use App\Infrastructure\CQRS\Bus\CommandHandler;

final readonly class RunOperationWithoutACommandCommandHandler implements CommandHandler
{
    public function handle(Command $command): void
    {
    }
}
