<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Etc;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SystemXmlTest extends TestCase
{
    public function testExecutionProfileSelectorIsExposedAtDefaultScopeWithExpectedSourceModel(): void
    {
        $document = new DOMDocument();
        $document->load(__DIR__ . '/../../../etc/adminhtml/system.xml');

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('/config/system/section[@id="admin"]/group[@id="chaos_donkey"]/field[@id="execution_profile"]');

        self::assertNotFalse($nodes);
        self::assertSame(1, $nodes->length, 'Expected execution_profile field to be defined');

        $field = $this->requireElement($nodes->item(0), 'Expected execution_profile field node to be a DOMElement');
        self::assertSame('select', $this->requireAttributeValue($field, 'type', 'Expected execution_profile type attribute'));
        self::assertSame('1', $this->requireAttributeValue($field, 'showInDefault', 'Expected execution_profile showInDefault attribute'));
        self::assertSame('0', $this->requireAttributeValue($field, 'showInWebsite', 'Expected execution_profile showInWebsite attribute'));
        self::assertSame('0', $this->requireAttributeValue($field, 'showInStore', 'Expected execution_profile showInStore attribute'));

        $sourceNodes = $xpath->query('source_model', $field);

        self::assertNotFalse($sourceNodes);
        self::assertSame(1, $sourceNodes->length);
        self::assertSame(
            'ShaunMcManus\\ChaosDonkey\\Model\\Config\\Source\\ExecutionProfileOptions',
            trim((string) $sourceNodes->item(0)->textContent)
        );
    }

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

        $field = $this->requireElement($nodes->item(0), sprintf('Expected field %s node to be a DOMElement', $fieldId));
        self::assertSame('select', $this->requireAttributeValue($field, 'type', sprintf('Expected %s type attribute', $fieldId)));
        self::assertSame('1', $this->requireAttributeValue($field, 'showInDefault', sprintf('Expected %s showInDefault attribute', $fieldId)));
        self::assertSame('0', $this->requireAttributeValue($field, 'showInWebsite', sprintf('Expected %s showInWebsite attribute', $fieldId)));
        self::assertSame('0', $this->requireAttributeValue($field, 'showInStore', sprintf('Expected %s showInStore attribute', $fieldId)));

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

    private function requireElement(?DOMNode $node, string $message): DOMElement
    {
        if (!$node instanceof DOMElement) {
            self::fail($message);
        }

        return $node;
    }

    private function requireAttributeValue(DOMElement $element, string $attributeName, string $message): string
    {
        $attribute = $element->attributes?->getNamedItem($attributeName);

        if ($attribute === null || $attribute->nodeValue === null) {
            self::fail($message);
        }

        return $attribute->nodeValue;
    }
}
