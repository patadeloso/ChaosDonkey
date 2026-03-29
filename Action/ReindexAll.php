<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Action;

use Magento\Indexer\Model\Indexer\CollectionFactory;
use ShaunMcManus\ChaosDonkey\Api\ChaosActionInterface;
use ShaunMcManus\ChaosDonkey\Model\ChaosActionResult;
use Throwable;
use Symfony\Component\Console\Output\OutputInterface;

class ReindexAll implements ChaosActionInterface
{
    private CollectionFactory $collectionFactory;

    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    public function getCode(): string
    {
        return 'reindex_all';
    }

    public function execute(OutputInterface $output): ChaosActionResult
    {
        $output->writeln('Reindexing all indexers...');
        $details = [];
        $hasFailure = false;

        foreach ($this->collectionFactory->create() as $indexer) {
            $indexerId = (string) $indexer->getId();

            $output->writeln(sprintf('Reindexing indexer: %s', $indexerId));

            try {
                $indexer->reindexAll();
                $output->writeln(sprintf('Done: %s', $indexerId));
                $details[] = sprintf('Done: %s', $indexerId);
            } catch (Throwable $exception) {
                $message = sprintf('Failed: %s (%s)', $indexerId, $exception->getMessage());
                $output->writeln($message);
                $details[] = $message;
                $hasFailure = true;
            }
        }

        return new ChaosActionResult(
            $this->getCode(),
            $hasFailure ? 'Reindex completed with failures' : 'Reindexed all indexers successfully',
            $details,
            !$hasFailure
        );
    }
}
