<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification\Ntfy;

use App\Infrastructure\ValueObject\String\Url;

interface Ntfy
{
    /**
     * @param array<string> $tags
     */
    public function sendNotification(
        string $title,
        string $notification,
        Url $click,
        array $tags,
    ): void;
}
