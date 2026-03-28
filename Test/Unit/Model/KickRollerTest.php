<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Model\KickRoller;

class KickRollerTest extends TestCase
{
    public function testRollD20ReturnsValuesWithinExpectedBounds(): void
    {
        $roller = new KickRoller();

        for ($i = 0; $i < 500; $i++) {
            $roll = $roller->rollD20();

            self::assertGreaterThanOrEqual(1, $roll);
            self::assertLessThanOrEqual(20, $roll);
        }
    }
}
