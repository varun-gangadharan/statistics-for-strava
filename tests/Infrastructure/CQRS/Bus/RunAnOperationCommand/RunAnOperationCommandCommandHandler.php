<?php

namespace App\Tests\Infrastructure\CQRS\Bus\RunAnOperationCommand;

use App\Infrastructure\CQRS\Bus\Command;
use App\Infrastructure\CQRS\Bus\CommandHandler;

final readonly class RunAnOperationCommandCommandHandler implements CommandHandler
{
    public function handle(Command $command): void
    {
    }
}
