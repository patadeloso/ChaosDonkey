<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Action;

use Magento\Indexer\Model\Indexer\CollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ShaunMcManus\ChaosDonkey\Action\ReindexAll;
use ShaunMcManus\ChaosDonkey\Model\ChaosActionResult;
use Symfony\Component\Console\Output\BufferedOutput;

class ReindexAllTest extends TestCase
{
    private CollectionFactory&MockObject $collectionFactory;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
    }

    public function testItReindexesAllIndexersAndPrintsProgress(): void
    {
        $firstIndexer = new FakeIndexer('catalog_product_price');
        $secondIndexer = new FakeIndexer('catalogsearch_fulltext');
        $output = new BufferedOutput();

        $this->collectionFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn([$firstIndexer, $secondIndexer]);

        $action = new ReindexAll($this->collectionFactory);

        $result = $action->execute($output);

        self::assertSame(1, $firstIndexer->reindexCalls);
        self::assertSame(1, $secondIndexer->reindexCalls);
        self::assertInstanceOf(ChaosActionResult::class, $result);
        self::assertTrue($result->isSuccess());
        self::assertSame('reindex_all', $result->getOutcomeCode());
        self::assertSame('Reindexed all indexers successfully', $result->getSummary());
        self::assertCount(2, $result->getDetails());
        $printedOutput = $output->fetch();

        self::assertStringContainsString('Reindexing all indexers...', $printedOutput);
        self::assertStringContainsString('Reindexing indexer: catalog_product_price', $printedOutput);
        self::assertStringContainsString('Done: catalog_product_price', $printedOutput);
        self::assertStringContainsString('Reindexing indexer: catalogsearch_fulltext', $printedOutput);
        self::assertStringContainsString('Done: catalogsearch_fulltext', $printedOutput);
    }

    public function testItContinuesReindexingWhenAnIndexerFails(): void
    {
        $failingIndexer = new FakeIndexer('failing_indexer', true);
        $healthyIndexer = new FakeIndexer('healthy_indexer');
        $output = new BufferedOutput();

        $this->collectionFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn([$failingIndexer, $healthyIndexer]);

        $action = new ReindexAll($this->collectionFactory);

        $result = $action->execute($output);

        self::assertSame(1, $failingIndexer->reindexCalls);
        self::assertSame(1, $healthyIndexer->reindexCalls);
        self::assertInstanceOf(ChaosActionResult::class, $result);
        self::assertFalse($result->isSuccess());
        self::assertSame('reindex_all', $result->getOutcomeCode());
        self::assertSame('Reindex completed with failures', $result->getSummary());
        self::assertCount(2, $result->getDetails());
        $printedOutput = $output->fetch();

        self::assertStringContainsString('Failed: failing_indexer (boom)', $printedOutput);
        self::assertStringContainsString('Done: healthy_indexer', $printedOutput);
    }
}

final class FakeIndexer
{
    public int $reindexCalls = 0;

    public function __construct(
        private string $id,
        private bool $throws = false
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function reindexAll(): void
    {
        $this->reindexCalls++;

        if ($this->throws) {
            throw new RuntimeException('boom');
        }
    }
}
