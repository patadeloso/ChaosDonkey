<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Action;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Indexer\Model\Indexer\CollectionFactory;

class ReindexAll
{
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public static function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Reindexing EVERYTHING!');

    }
}
