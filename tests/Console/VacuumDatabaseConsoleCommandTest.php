<?php

namespace App\Tests\Console;

use App\Console\VacuumDatabaseConsoleCommand;
use Doctrine\DBAL\Connection;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class VacuumDatabaseConsoleCommandTest extends ConsoleCommandTestCase
{
    use MatchesSnapshots;

    private VacuumDatabaseConsoleCommand $vacuumDatabaseConsoleCommand;

    public function testExecute(): void
    {
        $this->vacuumDatabaseConsoleCommand = new VacuumDatabaseConsoleCommand(
            $connection = $this->createMock(Connection::class),
        );

        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('VACUUM');

        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('VACUUM');

        $command = $this->getCommandInApplication('app:strava:vacuum');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertMatchesTextSnapshot($commandTester->getDisplay());
    }

    protected function getConsoleCommand(): Command
    {
        return $this->vacuumDatabaseConsoleCommand;
    }
}
