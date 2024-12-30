<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

use App\Infrastructure\ValueObject\Collection;

final class ActivityTypesToImport extends Collection
{
    public function getItemClassName(): string
    {
        return ActivityType::class;
    }

    /**
     * @param string[] $types
     */
    public static function from(array $types): self
    {
        if (0 === count($types)) {
            throw new \InvalidArgumentException('Ypu must import at least one type');
        }

        return self::fromArray(array_map(
            fn ($type) => ActivityType::from($type),
            $types
        ));
    }
}
