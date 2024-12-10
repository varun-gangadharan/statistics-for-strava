<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS\Bus;

class CanNotRegisterCommandHandler extends \RuntimeException
{
}
