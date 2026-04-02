<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model\Probe;

use DateTimeImmutable;
use DateTimeZone;

class SystemClock implements ClockInterface
{
    public function nowUtc(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
