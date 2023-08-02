<?php
declare(strict_types = 1);

namespace ShaunMcManus\ChaosDonkey\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ShaunMcManus\ChaosDonkey\Action\ReindexAll;

class ChaosDonkeyKick extends Command
{

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('chaosdonkey:kick')
            ->setDescription('Taunts ChaosDonkey into kicking your Magento');

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Code goes here ...
        // Needs RNG
        // Needs to launch chosen kick
        $kick = rand(1,20);
        //$kick = 2;

        $output->writeln('ChaosDonkeyKick kicks your Magento. You rolled a ' . $kick);
        match ($kick) {
            1 => $output->writeln('Critical Failure! Better check all of your donkeys.'),
            20 => $output->writeln('Critical Success! Yee Haw the donkeys are loose!'),
            2 => ReindexAll::execute($input, $output),
            default => $output->writeLn('The donkeys are napping'),
        };


        return 0;
    }
}


