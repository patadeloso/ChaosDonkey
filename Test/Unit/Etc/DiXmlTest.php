<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Etc;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class DiXmlTest extends TestCase
{
    public function testClockInterfacePreferencePointsToSystemClock(): void
    {
        $document = new DOMDocument();
        $document->load(__DIR__ . '/../../../etc/di.xml');

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query(
            '/config/preference[@for="ShaunMcManus\\ChaosDonkey\\Model\\Probe\\ClockInterface" '
            . 'and @type="ShaunMcManus\\ChaosDonkey\\Model\\Probe\\SystemClock"]'
        );

        self::assertNotFalse($nodes);
        self::assertSame(1, $nodes->length);
    }
}
