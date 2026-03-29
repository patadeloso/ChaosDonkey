<?php
declare(strict_types = 1);

namespace ShaunMcManus\ChaosDonkey\Console\Command;

use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\KickExecutor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChaosDonkeyKick extends Command
{
    private Config $config;
    private KickExecutor $kickExecutor;

    public function __construct(
        Config $config,
        KickExecutor $kickExecutor
    ) {
        parent::__construct();
        $this->config = $config;
        $this->kickExecutor = $kickExecutor;
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('chaosdonkey:kick')
            ->setDescription('Taunts ChaosDonkey into kicking your Magento');

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->config->isEnabled()) {
            $output->writeln('ChaosDonkey is disabled. Enable admin/chaos_donkey/enabled to kick.');

            return Command::SUCCESS;
        }

        $result = $this->kickExecutor->execute();

        foreach ($result['messages'] as $message) {
            $output->writeln($message);
        }

        return Command::SUCCESS;
    }
}
