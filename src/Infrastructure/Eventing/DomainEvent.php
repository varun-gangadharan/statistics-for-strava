<?php

declare(strict_types=1);

namespace App\Infrastructure\Eventing;

use Symfony\Contracts\EventDispatcher\Event;

abstract class DomainEvent extends Event
{
}
