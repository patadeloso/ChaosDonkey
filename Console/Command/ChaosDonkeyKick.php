<?php
declare(strict_types = 1);

namespace ShaunMcManus\ChaosDonkey\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChaosDonkey extends Command
{

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('chaosdonkey:kick')
            ->setDescription('A module that causes chaos randomly');

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Code goes here ...
        $output->writeln('ChaosDonkey kicks your Magento')
        return 0;
    }
}


