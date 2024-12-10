<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS\Deserializer;

use App\Infrastructure\CQRS\Bus\DomainCommand;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\NonEmptyStringLiteral;

class JsonCommandDeserializer implements CommandDeserializer
{
    /**
     * @throws \ReflectionException
     * @throws CanNotDeserializeCommand
     */
    public function deserialize(string $serialized): DomainCommand
    {
        $decoded = $this->decode($serialized);

        /** @var class-string<DomainCommand> $fqcn */
        $fqcn = $this->normalize($decoded['commandName'] ?? throw new CanNotDeserializeCommand('commandName is required'));
        if (!class_exists($fqcn)) {
            throw new CanNotDeserializeCommand("Class $fqcn does not exist. Did you include the full FQCN? Did you properly escape backslashes?");
        }

        $reflectionClass = new \ReflectionClass($fqcn);
        if (!$reflectionClass->getConstructor()) {
            throw new CanNotDeserializeCommand('The Command does not have a constructor');
        }

        $parameters = $reflectionClass->getConstructor()->getParameters();
        $arguments = $this->buildArgumentList($decoded['payload'], $parameters);

        return $reflectionClass->newInstanceArgs($arguments);
    }

    private function normalize(string $fqcn): string
    {
        $fqcn = str_replace('.', '\\', trim($fqcn));

        return (!str_starts_with($fqcn, '\\')) ? '\\'.$fqcn : $fqcn;
    }

    /**
     * @return array{commandName:string|null, payload:array<string, mixed>}
     *
     * @throws CanNotDeserializeCommand
     */
    private function decode(string $json): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = Json::decode($json);
        if (!isset($decoded['commandName'])) {
            throw new CanNotDeserializeCommand('Missing field commandName in json');
        }

        if (!is_string($decoded['commandName'])) {
            throw new CanNotDeserializeCommand('commandName should be a string');
        }

        if (!isset($decoded['payload'])) {
            throw new CanNotDeserializeCommand('Missing field payload in json');
        }

        // @phpstan-ignore-next-line
        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws CanNotDeserializeCommand
     */
    private function guardThatPayloadHasParameterIfRequired($payload, \ReflectionParameter $parameter): void
    {
        $payloadHasParameter = isset($payload[$parameter->getName()]);

        if (!$payloadHasParameter && !$parameter->isOptional() && !$parameter->allowsNull()) {
            throw new CanNotDeserializeCommand(sprintf('The parameter [%s] is missing from the Command payload. Add it to the payload or make it optional in the Command constructor.', $parameter->name));
        }
    }

    /**
     * @param array<string, mixed>   $payload
     * @param \ReflectionParameter[] $parameters
     *
     * @return array<int|string, mixed>
     *
     * @throws CanNotDeserializeCommand
     */
    private function buildArgumentList(array $payload, array $parameters)
    {
        $arguments = [];
        $remainingProperties = $payload;

        foreach ($parameters as $parameter) {
            $this->guardThatPayloadHasParameterIfRequired($payload, $parameter);
            unset($remainingProperties[$parameter->name]);
            $payloadHasParameter = isset($payload[$parameter->name]);

            if ($payloadHasParameter) {
                $arguments[] = $this->buildArgument(
                    $parameter,
                    $payload[$parameter->name],
                );
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            $arguments[] = null;
        }

        if (!empty($remainingProperties)) {
            throw new CanNotDeserializeCommand(sprintf('The parameters [%s] are never used in the Command payload. Remove them from the payload or make sure the Command\'s constructor has parameters with the same name.', implode(', ', array_keys($remainingProperties))));
        }

        return $arguments;
    }

    private function buildArgument(
        \ReflectionParameter $parameter,
        mixed $value,
    ): mixed {
        $argumentType = $parameter->getType();

        if (
            $argumentType instanceof \ReflectionNamedType
            && ($argumentTypeName = $argumentType->getName())
            && class_exists($argumentTypeName)
            && is_subclass_of($argumentTypeName, NonEmptyStringLiteral::class)
            && is_string($value)
        ) {
            return $argumentTypeName::fromString($value);
        }

        return $value;
    }
}
