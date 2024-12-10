<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimiting;

use App\Infrastructure\Time\Clock\Clock;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimit;

final readonly class ApplyRateLimitingListener implements EventSubscriberInterface
{
    public function __construct(
        private RateLimiterUsage $rateLimiterUsage,
        private Clock $clock,
    ) {
    }

    public function onKernelController(KernelEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        /** @var string $controllerClass */
        $controllerClass = $request->attributes->get('_controller');

        if (!$rateLimiter = $this->rateLimiterUsage->getRateLimiter($controllerClass)) {
            return;
        }

        $limit = $rateLimiter->create(sprintf('rate_limit_ip_%s', $request->getClientIp()))->consume();
        $request->attributes->set('rate_limit', $limit);

        if (false === $limit->isAccepted()) {
            throw new TooManyRequestsHttpException(message: 'Too many requests');
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (($rateLimit = $event->getRequest()->attributes->get('rate_limit')) instanceof RateLimit) {
            $event->getResponse()->headers->add([
                'RateLimit-Remaining' => max($rateLimit->getRemainingTokens(), 0),
                'X-RateLimit-Retry-After' => $rateLimit->getRetryAfter()->getTimestamp() - $this->clock->getCurrentDateTimeImmutable()->getTimestamp(),
                'RateLimit-Limit' => $rateLimit->getLimit(),
            ]);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 1024],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
