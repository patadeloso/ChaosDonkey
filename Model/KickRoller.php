<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model;

class KickRoller
{
    public function rollD20(): int
    {
        return random_int(1, 20);
    }
}
