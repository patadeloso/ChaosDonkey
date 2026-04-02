<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Etc;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class DiXmlTest extends TestCase
{
    public function testActionPoolContainsConfiguredProbeActions(): void
    {
        $document = new DOMDocument();
        $document->load(__DIR__ . '/../../../etc/di.xml');

        $xpath = new DOMXPath($document);

        $actionPoolEntries = [
            'indexer_status_snapshot' => 'ShaunMcManus\\ChaosDonkey\\Action\\IndexerStatusSnapshot',
            'cache_backend_health_snapshot' => 'ShaunMcManus\\ChaosDonkey\\Action\\CacheBackendHealthSnapshot',
            'cron_queue_health_snapshot' => 'ShaunMcManus\\ChaosDonkey\\Action\\CronQueueHealthSnapshot',
        ];

        foreach ($actionPoolEntries as $actionCode => $actionClass) {
            $nodes = $xpath->query(
                sprintf(
                    '/config/type[@name="ShaunMcManus\\ChaosDonkey\\Model\\ActionPool"]/arguments/argument[@name="actions"]'
                    . '/item[@name="%s" and @xsi:type="object" and normalize-space(text())="%s"]',
                    $actionCode,
                    $actionClass
                )
            );

            self::assertNotFalse($nodes);
            self::assertSame(1, $nodes->length, sprintf('Expected ActionPool item %s to be configured', $actionCode));
        }
    }

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
