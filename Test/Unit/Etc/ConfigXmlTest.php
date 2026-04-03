<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Etc;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class ConfigXmlTest extends TestCase
{
    public function testExecutionProfileDefaultsToBalanced(): void
    {
        $document = new DOMDocument();
        $document->load(__DIR__ . '/../../../etc/config.xml');

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('/config/default/admin/chaos_donkey/execution_profile');

        self::assertNotFalse($nodes);
        self::assertSame(1, $nodes->length, 'Expected execution_profile default node to exist');
        self::assertSame('balanced', trim((string) $nodes->item(0)->textContent));
    }

    public function testProbeDefaultsAreEnabledByDefault(): void
    {
        $document = new DOMDocument();
        $document->load(__DIR__ . '/../../../etc/config.xml');

        $xpath = new DOMXPath($document);

        $probeDefaults = [
            'enable_indexer_status_snapshot' => '1',
            'enable_cache_backend_health_snapshot' => '1',
            'enable_cron_queue_health_snapshot' => '1',
        ];

        foreach ($probeDefaults as $fieldId => $expectedValue) {
            $nodes = $xpath->query(sprintf('/config/default/admin/chaos_donkey/%s', $fieldId));

            self::assertNotFalse($nodes);
            self::assertSame(1, $nodes->length, sprintf('Expected default node for %s to exist', $fieldId));

            self::assertSame(
                $expectedValue,
                trim((string) $nodes->item(0)->textContent),
                sprintf('Expected default value for %s to be %s', $fieldId, $expectedValue)
            );
        }
    }
}
