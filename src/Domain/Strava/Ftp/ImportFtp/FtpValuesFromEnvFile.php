<?php

declare(strict_types=1);

namespace App\Domain\Strava\Ftp\ImportFtp;

use App\Domain\Strava\Ftp\Ftp;
use App\Domain\Strava\Ftp\Ftps;
use App\Domain\Strava\Ftp\FtpValue;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class FtpValuesFromEnvFile
{
    private Ftps $ftps;

    private function __construct(
        array $ftps,
    ) {
        $this->ftps = Ftps::empty();

        foreach ($ftps as $setOn => $ftpValue) {
            $this->ftps->add(Ftp::fromState(
                setOn: SerializableDateTime::fromString($setOn),
                ftp: FtpValue::fromInt($ftpValue)
            ));
        }
    }

    public function getAll(): Ftps
    {
        return $this->ftps;
    }

    public static function fromString(string $values): self
    {
        return new self(
            Json::decode($values)
        );
    }
}
