<?php

namespace App\Tests\Infrastructure\Serialization;

use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\TestCase;
use Safe\Exceptions\JsonException;
use Spatie\Snapshots\MatchesSnapshots;

class JsonTest extends TestCase
{
    use MatchesSnapshots;

    public function testEncodeDecode(): void
    {
        $array = ['random' => ['array' => ['with', 'children']]];

        $encoded = Json::encode($array);
        $this->assertMatchesJsonSnapshot($encoded);

        $this->assertEquals($array, Json::decode($encoded));
        $this->assertEquals($array, Json::encodeAndDecode($array));
    }

    public function testEncodeItShouldThrowOnInvalidValue(): void
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Type is not supported');

        $fp = fopen(__DIR__.'/test.txt', 'w');
        Json::encode($fp);
    }

    public function testDecodeItShouldThrowOnInvalidJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not decode json string: State mismatch (invalid or malformed JSON)
["invalid json"}');
        Json::decode('["invalid json"}');
    }
}
