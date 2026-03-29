<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model;

use Symfony\Component\Console\Output\BufferedOutput;

class KickExecutor
{
    private const MAX_REROLL_ATTEMPTS = 20;
    private const ACTION_CODES = [
        'reindex_all',
        'cache_flush',
        'graphql_pipeline_stress',
    ];

    public function __construct(
        private Config $config,
        private ActionPool $actionPool,
        private RollOutcomeResolver $resolver,
        private StateWriter $stateWriter,
        private KickRoller $kickRoller
    ) {
    }

    /**
     * @return array{
     *     kick: int,
     *     outcome: string,
     *     messages: list<string>
     * }
     */
    public function execute(): array
    {
        $messages = [];
        $enabledActions = $this->getEnabledActions();

        if (!in_array(true, $enabledActions, true)) {
            $messages[] = 'All configured chaos actions are disabled. Rolling non-action outcomes only.';
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
            $messages[] = 'Max reroll attempts reached. Falling back to napping.';
        }

        $messages[] = 'ChaosDonkeyKick kicks your Magento. You rolled a ' . $kick;

        $action = $this->actionPool->get($outcome);
        if ($action !== null) {
            $buffer = new BufferedOutput();
            $result = $action->execute($buffer);
            $bufferedOutput = trim($buffer->fetch());

            if ($bufferedOutput !== '') {
                foreach (preg_split('/\r\n|\r|\n/', $bufferedOutput) as $line) {
                    $messages[] = $line;
                }
            }

            $messages[] = $result->getSummary();
        } else {
            match ($outcome) {
                'critical_failure' => $messages[] = 'Critical Failure! Better check all of your donkeys.',
                'critical_success' => $messages[] = 'Critical Success! Yee Haw the donkeys are loose!',
                'napping' => $messages[] = 'The donkeys are napping',
                default => $messages[] = 'Unknown chaos outcome. The donkeys stare suspiciously.',
            };
        }

        $this->stateWriter->saveLastRun((new \DateTimeImmutable())->format(DATE_ATOM));
        $this->stateWriter->saveLastKick($kick);
        $this->stateWriter->saveLastOutcome($outcome);

        return [
            'kick' => $kick,
            'outcome' => $outcome,
            'messages' => $messages,
        ];
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
