<?php

namespace App\Tests\Domain\Strava;

use App\Domain\Strava\MaxStravaUsageHasBeenReached;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MaxStravaUsageHasBeenReachedTest extends TestCase
{
    private MaxStravaUsageHasBeenReached $maxStravaUsageHasBeenReached;
    private MockObject $filesystem;

    public function testClear(): void
    {
        $this->filesystem
            ->expects($this->once())
            ->method('delete')
            ->with('MAX_STRAVA_USAGE_REACHED');

        $this->maxStravaUsageHasBeenReached->clear();
    }

    public function testMarkAsReached(): void
    {
        $this->filesystem
            ->expects($this->once())
            ->method('write')
            ->with('MAX_STRAVA_USAGE_REACHED', '');

        $this->maxStravaUsageHasBeenReached->markAsReached();
    }

    public function testHasReached(): void
    {
        $this->filesystem
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->maxStravaUsageHasBeenReached->hasReached();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->maxStravaUsageHasBeenReached = new MaxStravaUsageHasBeenReached(
            $this->filesystem = $this->createMock(FilesystemOperator::class)
        );
    }
}
