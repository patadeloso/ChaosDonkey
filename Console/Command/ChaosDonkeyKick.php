<?php
declare(strict_types = 1);

namespace ShaunMcManus\ChaosDonkey\Console\Command;

use ShaunMcManus\ChaosDonkey\Model\ActionPool;
use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\KickRoller;
use ShaunMcManus\ChaosDonkey\Model\RollOutcomeResolver;
use ShaunMcManus\ChaosDonkey\Model\StateWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChaosDonkeyKick extends Command
{
    private Config $config;
    private ActionPool $actionPool;
    private RollOutcomeResolver $resolver;
    private StateWriter $stateWriter;
    private KickRoller $kickRoller;

    public function __construct(
        Config $config,
        ActionPool $actionPool,
        RollOutcomeResolver $resolver,
        StateWriter $stateWriter,
        KickRoller $kickRoller
    ) {
        parent::__construct();
        $this->config = $config;
        $this->actionPool = $actionPool;
        $this->resolver = $resolver;
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
        $outcome = $this->resolver->resolve($kick);
        $output->writeln('ChaosDonkeyKick kicks your Magento. You rolled a ' . $kick);

        $action = $this->actionPool->get($outcome);
        if ($action !== null) {
            $result = $action->execute($output);
            $output->writeln($result->getSummary());
        } else {
            match ($outcome) {
                'critical_failure' => $output->writeln('Critical Failure! Better check all of your donkeys.'),
                'critical_success' => $output->writeln('Critical Success! Yee Haw the donkeys are loose!'),
                'napping' => $output->writeln('The donkeys are napping'),
                default => $output->writeln('Unknown chaos outcome. The donkeys stare suspiciously.'),
            };
        }

        $this->stateWriter->saveLastRun((new \DateTimeImmutable())->format(DATE_ATOM));
        $this->stateWriter->saveLastKick($kick);
        $this->stateWriter->saveLastOutcome($outcome);

        return Command::SUCCESS;
    }
}
