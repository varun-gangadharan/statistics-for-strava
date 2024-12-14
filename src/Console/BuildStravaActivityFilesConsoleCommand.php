<?php

namespace App\Console;

use App\Domain\Strava\BuildHtmlVersion\BuildHtmlVersion;
use App\Domain\Strava\MaxStravaUsageHasBeenReached;
use App\Infrastructure\CQRS\Bus\CommandBus;
use App\Infrastructure\Time\ResourceUsage\ResourceUsage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:strava:build-files', description: 'Build Strava files')]
final class BuildStravaActivityFilesConsoleCommand extends Command
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly MaxStravaUsageHasBeenReached $maxStravaUsageHasBeenReached,
        private readonly ResourceUsage $resourceUsage,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->maxStravaUsageHasBeenReached->hasReached()) {
            $output->writeln('Reached Strava API rate limits, cannot build stats yet...');

            return Command::SUCCESS;
        }
        $this->resourceUsage->startTimer();

        $output->writeln('Building HTML...');
        $this->commandBus->dispatch(new BuildHtmlVersion($output));

        $this->resourceUsage->stopTimer();
        $output->writeln(sprintf(
            '<info>%s</info>',
            $this->resourceUsage->format(),
        ));

        return Command::SUCCESS;
    }
}
