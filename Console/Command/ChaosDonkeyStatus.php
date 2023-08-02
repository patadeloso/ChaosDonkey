<?php
declare(strict_types = 1);

namespace ShaunMcManus\ChaosDonkey\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChaosDonkeyStatus extends Command
{

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('chaosdonkey:status')
            ->setDescription('Show various config and statuses');

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Code goes here ...
        // Need to check various Config and Status stuff
        // Going to want to load the whole module config and status from Magento


        $output->writeln('Config and Status');
        $output->writeln('Enabled: ');
        $output->writeln('Running:');
        $output->writeln('Last run:');
        $output->writeln('Last kick:');

        return 0;
    }
}


