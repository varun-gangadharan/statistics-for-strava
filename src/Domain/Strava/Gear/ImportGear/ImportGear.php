<?php

namespace App\Domain\Strava\Gear\ImportGear;

use Symfony\Component\Console\Output\OutputInterface;

final class ImportGear extends DomainCommand
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
