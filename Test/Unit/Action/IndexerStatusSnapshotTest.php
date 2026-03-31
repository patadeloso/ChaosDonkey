<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Action;

use Magento\Indexer\Model\Indexer\CollectionFactory;
use Magento\Indexer\Model\IndexerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ShaunMcManus\ChaosDonkey\Action\IndexerStatusSnapshot;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeOutputFormatter;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class IndexerStatusSnapshotTest extends TestCase
{
    private CollectionFactory&MockObject $collectionFactory;
    private IndexerRegistry&MockObject $indexerRegistry;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->indexerRegistry = $this->createMock(IndexerRegistry::class);
    }

    public function testItWritesAllOkSummaryAndDetailsForHealthyIndexers(): void
    {
        $indexerMap = [
            'catalogsearch_fulltext' => new IndexerStatusSnapshotFakeIndexer('catalogsearch_fulltext', 'valid', false),
            'catalog_product_price' => new IndexerStatusSnapshotFakeIndexer('catalog_product_price', 'valid', true),
        ];
        $indexers = array_values($indexerMap);

        $this->collectionFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($indexers);

        $this->indexerRegistry
            ->expects(self::exactly(2))
            ->method('get')
            ->willReturnCallback(static function (string $indexerId) use ($indexerMap): IndexerStatusSnapshotFakeIndexer {
                return $indexerMap[$indexerId];
            });

        $result = $this->runProbe($indexers);
        $output = $result['output'];

        self::assertSame('indexer_status_snapshot', $result['instance']->getOutcomeCode());
        self::assertSame('', $result['instance']->getSummary());
        self::assertTrue($result['instance']->isSuccess());

        $lines = $this->splitOutput($output);

        self::assertCount(3, $lines);
        self::assertSame('Probe[indexer_status_snapshot] status=ok msg="2 indexers, 0 need reindex, modes: schedule=1, realtime=1"', $lines[0]);
        self::assertStringContainsString('subsystem=indexer item=catalogsearch_fulltext status=ok value="state=valid;mode=realtime"', $output);
        self::assertStringContainsString('subsystem=indexer item=catalog_product_price status=ok value="state=valid;mode=schedule"', $output);
    }

    public function testItReportsWarnSummaryAndRowsWhenIndexersNeedReindex(): void
    {
        $indexerMap = [
            'catalog_product_price' => new IndexerStatusSnapshotFakeIndexer('catalog_product_price', 'invalid', false),
            'catalogsearch_fulltext' => new IndexerStatusSnapshotFakeIndexer('catalogsearch_fulltext', 'valid', false),
        ];
        $indexers = array_values($indexerMap);

        $this->collectionFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($indexers);

        $this->indexerRegistry
            ->expects(self::exactly(2))
            ->method('get')
            ->willReturnCallback(static function (string $indexerId) use ($indexerMap): IndexerStatusSnapshotFakeIndexer {
                return $indexerMap[$indexerId];
            });

        $output = $this->runProbe($indexers)['output'];

        $lines = $this->splitOutput($output);

        self::assertSame('Probe[indexer_status_snapshot] status=warn msg="2 indexers, 1 need reindex, modes: schedule=0, realtime=2"', $lines[0]);
        self::assertStringContainsString('subsystem=indexer item=catalog_product_price status=warn value="state=invalid;mode=realtime"', $output);
    }

    public function testItKeepsWarnWhenInvalidAndUnknownRowsAreMixed(): void
    {
        $indexers = [
            new IndexerStatusSnapshotFakeIndexer('catalog_product_price', 'invalid', false),
            new IndexerStatusSnapshotFakeIndexer('catalogsearch_fulltext', 'valid', false, null, null, new RuntimeException('id failed')),
        ];

        $this->collectionFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($indexers);

        $this->indexerRegistry
            ->expects(self::once())
            ->method('get')
            ->with('catalog_product_price')
            ->willReturn($indexers[0]);

        $result = $this->runProbe($indexers);

        self::assertSame(
            'Probe[indexer_status_snapshot] status=warn msg="2 indexers, 1 need reindex, modes=unavailable"',
            $this->splitOutput($result['output'])[0]
        );
        self::assertTrue($result['instance']->isSuccess());
        self::assertStringContainsString('subsystem=indexer item=enumeration status=unknown value="unavailable"', $result['output']);
    }

    public function testItMarksUnknownWhenModeIsUnavailableEvenIfStateIsHealthy(): void
    {
        $indexers = [
            new IndexerStatusSnapshotFakeIndexer('catalogsearch_fulltext', 'valid', false, null, new RuntimeException('mode failed')),
        ];

        $this->collectionFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($indexers);

        $this->indexerRegistry
            ->expects(self::once())
            ->method('get')
            ->with('catalogsearch_fulltext')
            ->willReturn($indexers[0]);

        $result = $this->runProbe($indexers);

        $lines = $this->splitOutput($result['output']);

        self::assertSame(
            'Probe[indexer_status_snapshot] status=unknown msg="1 indexers, 0 need reindex, modes=unavailable"',
            $lines[0]
        );
        self::assertStringContainsString(
            'subsystem=indexer item=catalogsearch_fulltext status=unknown value="state=valid;mode=unavailable"',
            $result['output']
        );
        self::assertFalse($result['instance']->isSuccess());
    }

    public function testItMarksUnknownWhenStateCannotBeRead(): void
    {
        $indexers = [
            new IndexerStatusSnapshotFakeIndexer('catalogsearch_fulltext', 'valid', false, new RuntimeException('state failed')),
        ];

        $this->collectionFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($indexers);

        $this->indexerRegistry
            ->expects(self::once())
            ->method('get')
            ->with('catalogsearch_fulltext')
            ->willReturn($indexers[0]);

        $result = $this->runProbe($indexers);

        self::assertSame(
            'Probe[indexer_status_snapshot] status=unknown msg="1 indexers, 0 need reindex, modes=unavailable"',
            $this->splitOutput($result['output'])[0]
        );
        self::assertStringContainsString(
            'subsystem=indexer item=catalogsearch_fulltext status=unknown value="state=unavailable;mode=realtime"',
            $result['output']
        );
        self::assertFalse($result['instance']->isSuccess());
    }

    public function testItMarksUnknownWhenIndexerIdCannotBeRead(): void
    {
        $indexers = [
            new IndexerStatusSnapshotFakeIndexer('catalog_product_price', 'valid', false, null, null, new RuntimeException('id failed')),
        ];

        $this->collectionFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($indexers);

        $this->indexerRegistry
            ->expects(self::never())
            ->method('get');

        $result = $this->runProbe($indexers);

        self::assertSame(
            'Probe[indexer_status_snapshot] status=unknown msg="1 indexers, 0 need reindex, modes=unavailable"',
            $this->splitOutput($result['output'])[0]
        );
        self::assertStringContainsString(
            'ProbeDetail[indexer_status_snapshot] subsystem=indexer item=enumeration status=unknown value="unavailable"',
            $result['output']
        );
        self::assertFalse($result['instance']->isSuccess());
    }

    public function testItKeepsWarnStateWhenReindexNeededAndModeUnavailable(): void
    {
        $indexers = [
            new IndexerStatusSnapshotFakeIndexer('catalog_product_price', 'invalid', true, null, new RuntimeException('mode failed')),
        ];

        $this->collectionFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($indexers);

        $this->indexerRegistry
            ->expects(self::once())
            ->method('get')
            ->with('catalog_product_price')
            ->willReturn($indexers[0]);

        $result = $this->runProbe($indexers);

        self::assertTrue($result['instance']->isSuccess());
        self::assertStringContainsString(
            'Probe[indexer_status_snapshot] status=warn msg="1 indexers, 1 need reindex, modes=unavailable"',
            $result['output']
        );
        self::assertStringContainsString(
            'subsystem=indexer item=catalog_product_price status=warn value="state=invalid;mode=unavailable"',
            $result['output']
        );
    }

    public function testItFallsBackToUnknownWhenEnumerationFails(): void
    {
        $this->collectionFactory
            ->expects(self::once())
            ->method('create')
            ->willThrowException(new RuntimeException('indexer listing failed'));

        $this->indexerRegistry
            ->expects(self::never())
            ->method('get');

        $result = $this->runProbe([]);

        self::assertFalse($result['instance']->isSuccess());
        self::assertStringContainsString(
            'Probe[indexer_status_snapshot] status=unknown msg="n/a indexers, n/a need reindex, modes=unavailable"',
            $result['output']
        );
        self::assertStringContainsString(
            'ProbeDetail[indexer_status_snapshot] subsystem=indexer item=enumeration status=unknown value="unavailable"',
            $result['output']
        );
        self::assertCount(2, $this->splitOutput($result['output']));
    }

    public function testItFallsBackToUnknownWhenEnumerationThrowsDuringIteration(): void
    {
        $indexers = (function (): iterable {
            throw new RuntimeException('indexer enumeration failed');
            yield new IndexerStatusSnapshotFakeIndexer('should_not_be_reached', 'valid', false);
        })();

        $this->collectionFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($indexers);

        $this->indexerRegistry
            ->expects(self::never())
            ->method('get');

        $result = $this->runProbe([]);

        self::assertFalse($result['instance']->isSuccess());
        self::assertStringContainsString(
            'Probe[indexer_status_snapshot] status=unknown msg="n/a indexers, n/a need reindex, modes=unavailable"',
            $result['output']
        );
        self::assertStringContainsString(
            'ProbeDetail[indexer_status_snapshot] subsystem=indexer item=enumeration status=unknown value="unavailable"',
            $result['output']
        );
        self::assertCount(2, $this->splitOutput($result['output']));
    }

    public function testItLimitsOutputToSummaryPlusFiveTopDetails(): void
    {
        $indexers = [];
        for ($i = 1; $i <= 7; $i++) {
            $indexers[] = new IndexerStatusSnapshotFakeIndexer('indexer_' . $i, 'valid', $i % 2 === 0);
        }

        $this->collectionFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($indexers);

        $this->indexerRegistry
            ->expects(self::exactly(7))
            ->method('get')
            ->willReturnCallback(static function (string $indexerId) use ($indexers): IndexerStatusSnapshotFakeIndexer {
                $needle = array_filter(
                    $indexers,
                    static function (IndexerStatusSnapshotFakeIndexer $indexer) use ($indexerId): bool {
                        return $indexer->getId() === $indexerId;
                    }
                );

                return array_shift($needle);
            });

        $result = $this->runProbe($indexers);

        self::assertSame('indexer_status_snapshot', $result['instance']->getOutcomeCode());
        self::assertSame('', $result['instance']->getSummary());

        $lines = $this->splitOutput($result['output']);
        self::assertLessThanOrEqual(6, count($lines));
        self::assertSame('Probe[indexer_status_snapshot] status=ok msg="7 indexers, 0 need reindex, modes: schedule=3, realtime=4"', $lines[0]);
    }

    public function testEachDetailContainsStateAndModeTuple(): void
    {
        $indexers = [
            new IndexerStatusSnapshotFakeIndexer('catalog_product_price', 'valid', false),
            new IndexerStatusSnapshotFakeIndexer('catalogsearch_fulltext', 'valid', true),
        ];

        $this->collectionFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($indexers);

        $this->indexerRegistry
            ->expects(self::exactly(2))
            ->method('get')
            ->willReturnCallback(static function (string $indexerId) use ($indexers): IndexerStatusSnapshotFakeIndexer {
                if ($indexerId === 'catalogsearch_fulltext') {
                    return $indexers[1];
                }

                return $indexers[0];
            });

        $result = $this->runProbe($indexers);

        self::assertMatchesRegularExpression(
            '/subsystem=indexer item=catalog_product_price status=ok value="state=valid;mode=(realtime|schedule)"/',
            $result['output']
        );
        self::assertMatchesRegularExpression(
            '/subsystem=indexer item=catalogsearch_fulltext status=ok value="state=valid;mode=(realtime|schedule)"/',
            $result['output']
        );
    }

    private function runProbe(array $indexers): array
    {
        $output = new BufferedOutput();
        $action = new IndexerStatusSnapshot(
            $this->collectionFactory,
            $this->indexerRegistry,
            new ProbeOutputFormatter()
        );

        $result = $action->execute($output);

        return [
            'output' => trim($output->fetch()),
            'instance' => $result,
        ];
    }

    private function splitOutput(string $output): array
    {
        if ($output === '') {
            return [];
        }

        return preg_split('/\r\n|\r|\n/', $output);
    }
}

final class IndexerStatusSnapshotFakeIndexer
{
    public function __construct(
        private string $id,
        private string $status,
        private bool $scheduled,
        private ?Throwable $statusFailure = null,
        private ?Throwable $modeFailure = null,
        private ?Throwable $idFailure = null,
    ) {
    }

    public function getId(): string
    {
        if ($this->idFailure !== null) {
            throw $this->idFailure;
        }

        return $this->id;
    }

    public function getStatus(): string
    {
        if ($this->statusFailure !== null) {
            throw $this->statusFailure;
        }

        return $this->status;
    }

    public function isScheduled(): bool
    {
        if ($this->modeFailure !== null) {
            throw $this->modeFailure;
        }

        return $this->scheduled;
    }
}
