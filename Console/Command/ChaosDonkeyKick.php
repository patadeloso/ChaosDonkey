<?php
declare(strict_types = 1);

namespace ShaunMcManus\ChaosDonkey\Console\Command;

use ShaunMcManus\ChaosDonkey\Action\ReindexAll;
use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\KickRoller;
use ShaunMcManus\ChaosDonkey\Model\StateWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChaosDonkeyKick extends Command
{
    private Config $config;
    private ReindexAll $reindexAll;
    private StateWriter $stateWriter;
    private KickRoller $kickRoller;

    public function __construct(
        Config $config,
        ReindexAll $reindexAll,
        StateWriter $stateWriter,
        KickRoller $kickRoller
    ) {
        parent::__construct();
        $this->config = $config;
        $this->reindexAll = $reindexAll;
        $this->stateWriter = $stateWriter;
        $this->kickRoller = $kickRoller;
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

        $kick = $this->kickRoller->rollD20();
        $output->writeln('ChaosDonkeyKick kicks your Magento. You rolled a ' . $kick);

        $outcome = match ($kick) {
            1 => 'critical_failure',
            2 => 'reindex_all',
            20 => 'critical_success',
            default => 'napping',
        };

        match ($kick) {
            1 => $output->writeln('Critical Failure! Better check all of your donkeys.'),
            2 => $this->reindexAll->execute($output),
            20 => $output->writeln('Critical Success! Yee Haw the donkeys are loose!'),
            default => $output->writeln('The donkeys are napping'),
        };

        $this->stateWriter->saveLastRun((new \DateTimeImmutable())->format(DATE_ATOM));
        $this->stateWriter->saveLastKick($kick);
        $this->stateWriter->saveLastOutcome($outcome);

        return Command::SUCCESS;
    }
}
