<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\CQRS\Bus;

use App\Infrastructure\CQRS\Bus\Command;
use App\Infrastructure\CQRS\Bus\CommandBus;

class SpyCommandBus implements CommandBus
{
    /** @var \App\Infrastructure\CQRS\Bus\DomainCommand[] */
    private array $commandsToDispatch = [];

    public function dispatch(Command $command): void
    {
        $this->commandsToDispatch[] = $command;
    }

    /**
     * @return \App\Infrastructure\CQRS\Bus\DomainCommand[]
     */
    public function getDispatchedCommands(): array
    {
        $commandsToDispatch = $this->commandsToDispatch;
        $this->commandsToDispatch = [];

        return $commandsToDispatch;
    }

    public function getAvailableCommands(): array
    {
        return [];
    }
}
