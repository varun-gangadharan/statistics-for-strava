<?php

namespace App\Domain\Strava\BuildHtmlVersion;

use Symfony\Component\Console\Output\OutputInterface;

final class BuildHtmlVersion extends DomainCommand
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
