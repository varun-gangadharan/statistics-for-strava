<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Notification\Ntfy;

use App\Infrastructure\Notification\Ntfy\Ntfy;
use App\Infrastructure\ValueObject\String\Url;

class SpyNotify implements Ntfy
{
    public function sendNotification(string $title, string $message, array $tags, ?Url $click, ?Url $icon): void
    {
        // TODO: Implement sendNotification() method.
    }
}
