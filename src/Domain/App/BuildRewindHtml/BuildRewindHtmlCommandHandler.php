<?php

declare(strict_types=1);

namespace App\Domain\App\BuildRewindHtml;

use App\Infrastructure\CQRS\Command;
use App\Infrastructure\CQRS\CommandHandler;
use League\Flysystem\FilesystemOperator;
use Twig\Environment;

final readonly class BuildRewindHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private Environment $twig,
        private FilesystemOperator $buildStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildRewindHtml);

        $this->buildStorage->write(
            'rewind.html',
            $this->twig->load('html/rewind.html.twig')->render(),
        );
    }
}
