<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Etc;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class DbSchemaXmlTest extends TestCase
{
    public function testItDeclaresExecutionHistoryTableWithExpectedColumns(): void
    {
        $xpath = $this->createXPath();

        $historyId = $this->querySingleNode(
            $xpath,
            "/schema/table[@name='shaunmcmanus_chaosdonkey_execution_history']/column[@name='history_id']"
        );
        self::assertSame('int', $historyId->getAttribute('xsi:type'));
        self::assertSame('true', $historyId->getAttribute('unsigned'));
        self::assertSame('true', $historyId->getAttribute('identity'));
        self::assertSame('false', $historyId->getAttribute('nullable'));

        $executedAt = $this->querySingleNode(
            $xpath,
            "/schema/table[@name='shaunmcmanus_chaosdonkey_execution_history']/column[@name='executed_at']"
        );
        self::assertSame('datetime', $executedAt->getAttribute('xsi:type'));
        self::assertSame('false', $executedAt->getAttribute('nullable'));

        $source = $this->querySingleNode(
            $xpath,
            "/schema/table[@name='shaunmcmanus_chaosdonkey_execution_history']/column[@name='source']"
        );
        self::assertSame('varchar', $source->getAttribute('xsi:type'));
        self::assertSame('16', $source->getAttribute('length'));
        self::assertSame('false', $source->getAttribute('nullable'));

        $kick = $this->querySingleNode(
            $xpath,
            "/schema/table[@name='shaunmcmanus_chaosdonkey_execution_history']/column[@name='kick']"
        );
        self::assertSame('int', $kick->getAttribute('xsi:type'));
        self::assertSame('true', $kick->getAttribute('unsigned'));
        self::assertSame('false', $kick->getAttribute('nullable'));

        $outcome = $this->querySingleNode(
            $xpath,
            "/schema/table[@name='shaunmcmanus_chaosdonkey_execution_history']/column[@name='outcome']"
        );
        self::assertSame('varchar', $outcome->getAttribute('xsi:type'));
        self::assertSame('64', $outcome->getAttribute('length'));
        self::assertSame('false', $outcome->getAttribute('nullable'));

        $configuredProfile = $this->querySingleNode(
            $xpath,
            "/schema/table[@name='shaunmcmanus_chaosdonkey_execution_history']/column[@name='configured_profile']"
        );
        self::assertSame('varchar', $configuredProfile->getAttribute('xsi:type'));
        self::assertSame('32', $configuredProfile->getAttribute('length'));
        self::assertSame('false', $configuredProfile->getAttribute('nullable'));

        $effectiveProfile = $this->querySingleNode(
            $xpath,
            "/schema/table[@name='shaunmcmanus_chaosdonkey_execution_history']/column[@name='effective_profile']"
        );
        self::assertSame('varchar', $effectiveProfile->getAttribute('xsi:type'));
        self::assertSame('32', $effectiveProfile->getAttribute('length'));
        self::assertSame('false', $effectiveProfile->getAttribute('nullable'));

        $fallbackReason = $this->querySingleNode(
            $xpath,
            "/schema/table[@name='shaunmcmanus_chaosdonkey_execution_history']/column[@name='fallback_reason']"
        );
        self::assertSame('varchar', $fallbackReason->getAttribute('xsi:type'));
        self::assertSame('64', $fallbackReason->getAttribute('length'));
        self::assertSame('true', $fallbackReason->getAttribute('nullable'));
    }

    public function testItDeclaresPrimaryKeyForExecutionHistoryTable(): void
    {
        $xpath = $this->createXPath();

        $primaryKey = $xpath->query(
            "/schema/table[@name='shaunmcmanus_chaosdonkey_execution_history']/constraint[@xsi:type='primary']/column[@name='history_id']"
        );

        self::assertCount(1, $primaryKey);
    }

    private function createXPath(): DOMXPath
    {
        $filePath = dirname(__DIR__, 3) . '/etc/db_schema.xml';

        self::assertFileExists($filePath);

        $document = new DOMDocument();
        $document->load($filePath);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('xsi', 'http://www.w3.org/2001/XMLSchema-instance');

        return $xpath;
    }

    private function querySingleNode(DOMXPath $xpath, string $expression): \DOMElement
    {
        $nodes = $xpath->query($expression);

        self::assertCount(1, $nodes);
        self::assertInstanceOf(\DOMElement::class, $nodes->item(0));

        return $nodes->item(0);
    }
}
