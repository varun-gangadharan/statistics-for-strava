<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimiting;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RateLimiterCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ApplyRateLimitingListener::class)) {
            throw new \LogicException(sprintf('Can not configure non-existent service %s', ApplyRateLimitingListener::class));
        }

        $controllers = $container->findTaggedServiceIds('controller.service_arguments');
        /** @var \Symfony\Component\DependencyInjection\Definition[] $serviceDefinitions */
        $serviceDefinitions = array_map(fn (string $id) => $container->getDefinition($id), array_keys($controllers));

        $rateLimiterUsage = $container->findDefinition(RateLimiterUsage::class);

        foreach ($serviceDefinitions as $serviceDefinition) {
            $controllerClass = $serviceDefinition->getClass();
            $reflectionClass = $container->getReflectionClass($controllerClass);

            foreach ($reflectionClass?->getMethods(\ReflectionMethod::IS_PUBLIC) ?? [] as $reflectionMethod) {
                $attributes = $reflectionMethod->getAttributes(RateLimiter::class);
                if (0 === \count($attributes)) {
                    continue;
                }
                [$attribute] = $attributes;

                $serviceKey = sprintf('limiter.%s', $attribute->newInstance()->configuration);
                if (!$container->hasDefinition($serviceKey)) {
                    throw new \RuntimeException(sprintf('Service "%s" not found', $serviceKey));
                }

                $classMapKey = sprintf('%s::%s', $serviceDefinition->getClass(), $reflectionMethod->getName());
                $rateLimiterUsage->addMethodCall('addRateLimiter', [$classMapKey, new Reference($serviceKey)]);
            }
        }
    }
}
