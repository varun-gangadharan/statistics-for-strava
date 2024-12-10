<?php

declare(strict_types=1);

namespace App\Domain\Strava\Segment\ImportSegments;

use Symfony\Component\Console\Output\OutputInterface;

final class ImportSegments extends DomainCommand
{
    public function __construct(
        private readonly OutputInterface $output,
    ) {
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }
}
