<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Action;

use Magento\Indexer\Model\Indexer\CollectionFactory;
use Magento\Indexer\Model\IndexerRegistry;
use ShaunMcManus\ChaosDonkey\Api\ChaosActionInterface;
use ShaunMcManus\ChaosDonkey\Model\ChaosActionResult;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeDetailRow;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeOutputFormatter;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeSnapshot;
use Throwable;
use Symfony\Component\Console\Output\OutputInterface;

class IndexerStatusSnapshot implements ChaosActionInterface
{
    public function __construct(
        private CollectionFactory $collectionFactory,
        private IndexerRegistry $indexerRegistry,
        private ProbeOutputFormatter $probeOutputFormatter
    ) {
    }

    public function getCode(): string
    {
        return 'indexer_status_snapshot';
    }

    public function execute(OutputInterface $output): ChaosActionResult
    {
        $snapshot = $this->snapshotStatuses();

        $output->writeln($this->probeOutputFormatter->formatLines($snapshot));

        return new ChaosActionResult(
            $this->getCode(),
            '',
            [],
            $snapshot->getStatus() !== 'unknown'
        );
    }

    private function snapshotStatuses(): ProbeSnapshot
    {
        try {
            $indexers = $this->collectionFactory->create();
        } catch (Throwable $exception) {
            return new ProbeSnapshot(
                $this->getCode(),
                'unknown',
                'n/a indexers, n/a need reindex, modes=unavailable',
                [
                    new ProbeDetailRow('indexer', 'enumeration', 'unknown', 'unavailable'),
                ]
            );
        }

        $details = [];
        $indexerCount = 0;
        $needsReindex = 0;
        $unavailableModeCount = 0;
        $unavailableStateCount = 0;
        $scheduledModeCount = 0;
        $realtimeModeCount = 0;

        foreach ($indexers as $indexer) {
            $indexerCount++;

            $indexerId = 'indexer';

            try {
                $indexerId = (string) $indexer->getId();
            } catch (Throwable $exception) {
                $unavailableStateCount++;
                $details[] = new ProbeDetailRow(
                    'indexer',
                    'enumeration',
                    'unknown',
                    'unavailable'
                );

                continue;
            }

            $state = 'unavailable';
            $mode = 'unavailable';
            $detailStatus = 'unknown';

            try {
                $registryIndexer = $this->indexerRegistry->get($indexerId);

                try {
                    $state = (string) $registryIndexer->getStatus();
                } catch (Throwable $exception) {
                    $state = 'unavailable';
                    $unavailableStateCount++;
                }

                try {
                    $mode = $registryIndexer->isScheduled() ? 'schedule' : 'realtime';
                } catch (Throwable $exception) {
                    $mode = 'unavailable';
                }
            } catch (Throwable $exception) {
                $state = 'unavailable';
                $mode = 'unavailable';
                $unavailableStateCount++;
            }

            if ($mode === 'unavailable') {
                $unavailableModeCount++;
            } elseif ($mode === 'schedule') {
                $scheduledModeCount++;
            } else {
                $realtimeModeCount++;
            }

            if ($state === 'unavailable') {
                $detailStatus = 'unknown';
            } elseif ($this->requiresReindex($state)) {
                $detailStatus = 'warn';
                $needsReindex++;
            } elseif ($mode === 'unavailable') {
                $detailStatus = 'unknown';
            } else {
                $detailStatus = 'ok';
            }

            $details[] = new ProbeDetailRow(
                'indexer',
                $indexerId,
                $detailStatus,
                sprintf('state=%s;mode=%s', $state, $mode)
            );
        }

        $overallStatus = 'ok';
        if ($unavailableStateCount > 0) {
            $overallStatus = 'unknown';
        } elseif ($needsReindex > 0) {
            $overallStatus = 'warn';
        } elseif ($unavailableModeCount > 0) {
            $overallStatus = 'unknown';
        }

        $summary = sprintf(
            '%d indexers, %d need reindex, modes=%s',
            $indexerCount,
            $needsReindex,
            'unavailable'
        );

        if ($unavailableStateCount === 0 && $unavailableModeCount === 0) {
            $summary = sprintf(
                '%d indexers, %d need reindex, modes: schedule=%d, realtime=%d',
                $indexerCount,
                $needsReindex,
                $scheduledModeCount,
                $realtimeModeCount
            );
        }

        return new ProbeSnapshot(
            $this->getCode(),
            $overallStatus,
            $summary,
            $details
        );
    }

    private function requiresReindex(string $state): bool
    {
        return in_array(strtolower((string) $state), ['invalid', 'reindex-required', 'reindex_required'], true);
    }
}
