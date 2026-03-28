<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Action;

use Magento\Indexer\Model\Indexer\CollectionFactory;
use Throwable;
use Symfony\Component\Console\Output\OutputInterface;

class ReindexAll
{
    private CollectionFactory $collectionFactory;

    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    public function execute(OutputInterface $output): void
    {
        $output->writeln('Reindexing all indexers...');

        foreach ($this->collectionFactory->create() as $indexer) {
            $indexerId = (string) $indexer->getId();

            $output->writeln(sprintf('Reindexing indexer: %s', $indexerId));

            try {
                $indexer->reindexAll();
                $output->writeln(sprintf('Done: %s', $indexerId));
            } catch (Throwable $exception) {
                $output->writeln(
                    sprintf('Failed: %s (%s)', $indexerId, $exception->getMessage())
                );
            }
        }
    }
}
