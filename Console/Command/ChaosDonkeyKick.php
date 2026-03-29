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
    private const MAX_REROLL_ATTEMPTS = 20;
    private const ACTION_CODES = [
        'reindex_all',
        'cache_flush',
        'graphql_pipeline_stress',
    ];

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

        $enabledActions = $this->getEnabledActions();
        if (!in_array(true, $enabledActions, true)) {
            $output->writeln('All configured chaos actions are disabled. Rolling non-action outcomes only.');
        }

        $kick = 0;
        $outcome = 'napping';

        for ($attempt = 0; $attempt < self::MAX_REROLL_ATTEMPTS; $attempt++) {
            $kick = $this->kickRoller->rollD20();
            $outcome = $this->resolver->resolve($kick);

            if (!$this->isDisabledActionOutcome($outcome, $enabledActions)) {
                break;
            }
        }

        if ($this->isDisabledActionOutcome($outcome, $enabledActions)) {
            $outcome = 'napping';
            $output->writeln('Max reroll attempts reached. Falling back to napping.');
        }

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

    /**
     * @return array<string, bool>
     */
    private function getEnabledActions(): array
    {
        $enabledActions = [];

        foreach (self::ACTION_CODES as $actionCode) {
            $enabledActions[$actionCode] = $this->config->isActionEnabled($actionCode);
        }

        return $enabledActions;
    }

    /**
     * @param array<string, bool> $enabledActions
     */
    private function isDisabledActionOutcome(string $outcome, array $enabledActions): bool
    {
        if (!array_key_exists($outcome, $enabledActions)) {
            return false;
        }

        return $enabledActions[$outcome] === false;
    }
}
