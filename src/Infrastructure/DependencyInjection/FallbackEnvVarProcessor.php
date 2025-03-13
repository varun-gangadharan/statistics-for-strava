<?php

declare(strict_types=1);

namespace App\Infrastructure\DependencyInjection;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

final readonly class FallbackEnvVarProcessor implements EnvVarProcessorInterface
{
    /**
     * @return array<mixed>|float|int|string|false|null
     */
    public function getEnv(string $prefix, string $name, \Closure $getEnv): array|float|int|string|false|null
    {
        $fallbacks = explode(':', $name);
        if (1 === count($fallbacks)) {
            return $getEnv($name);
        }

        $env = null;
        foreach ($fallbacks as $fallback) {
            if ($env = $getEnv($fallback)) {
                return $env;
            }
        }

        return $env;
    }

    public static function getProvidedTypes(): array
    {
        return [
            'fallback' => 'bool|int|float|string|array',
        ];
    }
}
