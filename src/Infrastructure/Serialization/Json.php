<?php

declare(strict_types=1);

namespace App\Infrastructure\Serialization;

use Safe\Exceptions\JsonException;

class Json
{
    public static function encode(mixed $value, int $options = 0, int $depth = 512): string
    {
        try {
            return \Safe\json_encode($value, $options, $depth);
        } catch (JsonException $exception) {
            throw new JsonException($exception->getMessage(), $exception->getCode(), $exception->getPrevious());
        }
    }

    public static function decode(string $json, bool $assoc = true, int $depth = 512, int $options = 0): mixed
    {
        try {
            // @phpstan-ignore-next-line
            return \Safe\json_decode($json ?: '', $assoc, $depth, $options);
        } catch (\Exception $exception) {
            throw new \InvalidArgumentException('Could not decode json string: '.$exception->getMessage().\PHP_EOL.\substr($json, 0, 1000));
        }
    }

    public static function encodeAndDecode(mixed $value, int $options = 0, int $depth = 512): mixed
    {
        return self::decode(self::encode($value, $options, $depth));
    }
}
