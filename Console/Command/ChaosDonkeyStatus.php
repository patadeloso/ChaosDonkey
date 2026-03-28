<?php
declare(strict_types = 1);

namespace ShaunMcManus\ChaosDonkey\Console\Command;

use ShaunMcManus\ChaosDonkey\Model\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChaosDonkeyStatus extends Command
{
    private Config $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('chaosdonkey:status')
            ->setDescription('Show various config and statuses');

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $enabled = $this->config->isEnabled() ? 'Yes' : 'No';
        $lastRun = $this->config->getLastRun() ?? 'Never';
        $lastKick = $this->config->getLastKick() ?? 'Never';
        $lastOutcome = $this->config->getLastOutcome() ?? 'Never';

        $output->writeln('ChaosDonkey Status');
        $output->writeln('Enabled: ' . $enabled);
        $output->writeln('Last run: ' . $lastRun);
        $output->writeln('Last kick: ' . $lastKick);
        $output->writeln('Last outcome: ' . $lastOutcome);

        return Command::SUCCESS;
    }
}

