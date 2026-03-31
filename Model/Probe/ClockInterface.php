<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model\Probe;

use DateTimeImmutable;

interface ClockInterface
{
    public function nowUtc(): DateTimeImmutable;
}
