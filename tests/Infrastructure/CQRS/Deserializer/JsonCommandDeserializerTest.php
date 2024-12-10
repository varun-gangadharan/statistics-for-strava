<?php

namespace App\Tests\Infrastructure\CQRS\Deserializer;

use App\Infrastructure\CQRS\Deserializer\CanNotDeserializeCommand;
use App\Infrastructure\CQRS\Deserializer\JsonCommandDeserializer;
use App\Infrastructure\ValueObject\String\Url;
use App\Tests\Infrastructure\CQRS\Bus\RunAnOperation\RunAnOperation;
use App\Tests\Infrastructure\CQRS\Bus\TestCommandWithValueObject;
use PHPUnit\Framework\TestCase;

class JsonCommandDeserializerTest extends TestCase
{
    private JsonCommandDeserializer $jsonCommandDeserializer;

    public function testItShouldDeserialize(): void
    {
        $json = <<<JSON
        
        {
            "commandName": "App.Tests.Infrastructure.CQRS.Bus.RunAnOperation.RunAnOperation", 
            "payload": {
                "value": "a string"
            }
        }
        
JSON;

        $this->assertInstanceOf(
            RunAnOperation::class,
            $this->jsonCommandDeserializer->deserialize($json)
        );
    }

    public function testItShouldAcceptNullForOptionalParameter(): void
    {
        $json = <<<JSON
     {
            "commandName": "App.Tests.Infrastructure.CQRS.Bus.RunAnOperation.RunAnOperation", 
            "payload": {
                "value": "a string",
                "valueTwo": null
            }
        }
JSON;

        $command = $this->jsonCommandDeserializer->deserialize($json);

        $this->assertInstanceOf(RunAnOperation::class, $command);
        $this->assertEquals('defaultValue', $command->getValueTwo());
    }

    public function testItShouldAcceptNullForNullableNonOptionalParameter(): void
    {
        $json = <<<JSON
           {
            "commandName": "App.Tests.Infrastructure.CQRS.Bus.RunAnOperation.RunAnOperation", 
            "payload": {
                "value":null
            }
        }
JSON;

        $this->assertInstanceOf(
            RunAnOperation::class,
            $this->jsonCommandDeserializer->deserialize($json)
        );
    }

    public function testItShouldAcceptStringForValueObjectParameter(): void
    {
        $json = <<<JSON
        {
            "commandName": "App.Tests.Infrastructure.CQRS.Bus.TestCommandWithValueObject",
            "payload": {
                "value": "http://example.com"
            }
        }
JSON;

        $command = $this->jsonCommandDeserializer->deserialize($json);

        $this->assertInstanceOf(TestCommandWithValueObject::class, $command);

        $this->assertEquals(
            Url::fromString('http://example.com'),
            $command->getValue(),
        );
    }

    public function testItShouldThrowWithAlienProperties(): void
    {
        $json = <<<JSON
        
        {
           "commandName": "App.Tests.Infrastructure.CQRS.Bus.RunAnOperation.RunAnOperation", 
            "payload": {
                "value": "a string",
                "alienValue": "a string"
            }
        }
        
JSON;

        $this->expectExceptionObject(new CanNotDeserializeCommand("The parameters [alienValue] are never used in the Command payload. Remove them from the payload or make sure the Command's constructor has parameters with the same name."));
        $this->jsonCommandDeserializer->deserialize($json);
    }

    public function testItShouldThrowWhenPayloadIsMissing(): void
    {
        $json = <<<JSON
        
        {
            "commandName": "App.Tests.Infrastructure.CQRS.Bus.RunAnOperation.RunAnOperation"
        }
        
JSON;

        $this->expectExceptionObject(new CanNotDeserializeCommand('Missing field payload in json'));
        $this->jsonCommandDeserializer->deserialize($json);
    }

    public function testItShouldThrowWhenCommandNameIsMissing(): void
    {
        $json = <<<JSON
        
        {
            "payload": {
                "value": "a string"
            }
        }
        
JSON;

        $this->expectExceptionObject(new CanNotDeserializeCommand('Missing field commandName in json'));
        $this->jsonCommandDeserializer->deserialize($json);
    }

    public function testItShouldThrowWhenFieldIsMissingFromPayload(): void
    {
        $json = <<<JSON
        
        {
            "commandName": "App.Tests.Infrastructure.CQRS.Bus.TestCommandWithValueObject",
            "payload": {}
        }
        
JSON;

        $this->expectExceptionObject(new CanNotDeserializeCommand('The parameter [value] is missing from the Command payload. Add it to the payload or make it optional in the Command constructor.'));
        $this->jsonCommandDeserializer->deserialize($json);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->jsonCommandDeserializer = new JsonCommandDeserializer();
    }
}
