<?php

namespace App\Tests\Domain\App\BuildRewindHtml;

use App\Domain\App\BuildRewindHtml\BuildRewindHtml;
use App\Tests\Domain\App\BuildAppFilesTestCase;

class BuildRewindHtmlCommandHandlerTest extends BuildAppFilesTestCase
{
    public function testHandle(): void
    {
        $this->commandBus->dispatch(new BuildRewindHtml());
        $this->assertFileSystemWrites($this->getContainer()->get('build.storage'));
    }
}
