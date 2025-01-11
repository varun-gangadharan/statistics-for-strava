<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification\Ntfy;

use App\Infrastructure\ValueObject\String\Url;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

final readonly class LiveNtfy implements Ntfy
{
    public function __construct(
        private Client $client,
        private NtfyUrl $ntfyUrl,
    ) {
    }

    /**
     * @param array<string> $tags
     */
    public function sendNotification(
        string $title,
        string $notification,
        Url $click,
        array $tags,
    ): void {
        $this->client->request(
            'POST',
            (string) $this->ntfyUrl,
            [
                RequestOptions::HEADERS => [
                    'Content-Type' => 'text/plain',
                    'Title' => $title,
                    'Tags' => implode(',', $tags),
                    'Click' => (string) $click,
                ],
                RequestOptions::BODY => [
                    'content' => $notification,
                ],
            ]
        );
    }
}
