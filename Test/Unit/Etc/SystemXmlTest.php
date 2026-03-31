<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Etc;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SystemXmlTest extends TestCase
{
    #[DataProvider('probeFieldProvider')]
    public function testProbeTogglesExistAndUseExpectedDefaultsAndSource(
        string $fieldId,
        string $expectedLabel,
        string $expectedComment
    ): void {
        $document = new DOMDocument();
        $document->load(__DIR__ . '/../../../etc/adminhtml/system.xml');

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query(sprintf(
            '/config/system/section[@id="admin"]/group[@id="chaos_donkey"]/field[@id="%s"]',
            $fieldId
        ));

        self::assertNotFalse($nodes);
        self::assertSame(1, $nodes->length, sprintf('Expected field %s to be defined', $fieldId));

        $field = $nodes->item(0);

        self::assertInstanceOf(\DOMElement::class, $field);
        self::assertSame('select', $field->getAttribute('type'));
        self::assertSame('1', $field->getAttribute('showInDefault'));
        self::assertSame('0', $field->getAttribute('showInWebsite'));
        self::assertSame('0', $field->getAttribute('showInStore'));

        $labelNodes = $xpath->query('label', $field);
        self::assertNotFalse($labelNodes);
        self::assertSame(1, $labelNodes->length);
        self::assertSame($expectedLabel, trim((string) $labelNodes->item(0)->textContent));

        $sourceNodes = $xpath->query('source_model', $field);
        self::assertNotFalse($sourceNodes);
        self::assertSame(1, $sourceNodes->length);
        self::assertSame('Magento\\Config\\Model\\Config\\Source\\Yesno', trim((string) $sourceNodes->item(0)->textContent));

        $commentNodes = $xpath->query('comment', $field);
        self::assertNotFalse($commentNodes);
        self::assertSame(1, $commentNodes->length);
        self::assertSame($expectedComment, trim((string) $commentNodes->item(0)->textContent));
    }

    public static function probeFieldProvider(): array
    {
        return [
            'indexer status snapshot' => [
                'enable_indexer_status_snapshot',
                'Enable Indexer Status Snapshot',
                'When enabled, the command can trigger an indexer status snapshot probe.',
            ],
            'cache backend health snapshot' => [
                'enable_cache_backend_health_snapshot',
                'Enable Cache Backend Health Snapshot',
                'When enabled, the command can trigger a cache backend health probe.',
            ],
            'cron queue health snapshot' => [
                'enable_cron_queue_health_snapshot',
                'Enable Cron/Queue Health Snapshot',
                'When enabled, the command can trigger a cron and queue health probe.',
            ],
        ];
    }
}
