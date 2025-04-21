<?php

declare(strict_types=1);

namespace App\Domain\Strava\Rewind;

final readonly class RewindItem
{
    private function __construct(
        private string $icon,
        private string $title,
        private ?string $subTitle,
        private string $content,
        private int $colSpan,
    ) {
    }

    public static function from(
        string $icon,
        string $title,
        ?string $subTitle,
        string $content,
        int $colSpan = 1,
    ): self {
        return new self(
            icon: $icon,
            title: $title,
            subTitle: $subTitle,
            content: $content,
            colSpan: $colSpan
        );
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSubTitle(): ?string
    {
        return $this->subTitle;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getColSpan(): int
    {
        return $this->colSpan;
    }
}
