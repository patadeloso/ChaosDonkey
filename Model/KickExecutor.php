<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model;

use ShaunMcManus\ChaosDonkey\Model\Profile\ProfiledRollSelector;
use Symfony\Component\Console\Output\BufferedOutput;

class KickExecutor
{
    /**
     * Canonical outcome order. Keep aligned with OutcomeCatalog::getOutcomeCodes().
     */
    private const OUTCOME_CODES = [
        'critical_failure',
        'reindex_all',
        'cache_flush',
        'graphql_pipeline_stress',
        'indexer_status_snapshot',
        'cache_backend_health_snapshot',
        'cron_queue_health_snapshot',
        'napping',
        'critical_success',
    ];

    private const ACTION_CODES = [
        'reindex_all',
        'cache_flush',
        'graphql_pipeline_stress',
        'indexer_status_snapshot',
        'cache_backend_health_snapshot',
        'cron_queue_health_snapshot',
    ];

    public function __construct(
        private Config $config,
        private ActionPool $actionPool,
        private ProfiledRollSelector $profiledRollSelector,
        private StateWriter $stateWriter,
        private KickRoller $kickRoller,
        private ExecutionHistoryStorage $executionHistoryStorage
    ) {
    }

    /**
     * @return array{
     *     kick: int,
     *     outcome: string,
     *     messages: list<string>
     * }
     */
    public function execute(string $source): array
    {
        $messages = [];
        $enabledActions = $this->getEnabledActions();
        $eligibleOutcomeCodes = $this->buildEligibleOutcomeCodes($enabledActions);

        if (!in_array(true, $enabledActions, true)) {
            $messages[] = 'All configured chaos actions/probes are disabled. Rolling non-action outcomes only.';
        }

        $kick = $this->kickRoller->rollD20();
        $selection = $this->profiledRollSelector->resolveForSlot(
            $this->config->getExecutionProfile(),
            $eligibleOutcomeCodes,
            $kick
        );
        $outcome = $selection['selected_outcome'];

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

            $summary = $result->getSummary();

            if ($summary !== '') {
                $messages[] = $summary;
            }
        } else {
            match ($outcome) {
                'critical_failure' => $messages[] = 'Critical Failure! Better check all of your donkeys.',
                'critical_success' => $messages[] = 'Critical Success! Yee Haw the donkeys are loose!',
                'napping' => $messages[] = 'The donkeys are napping',
                default => $messages[] = 'Unknown chaos outcome. The donkeys stare suspiciously.',
            };
        }

        $executedAt = new \DateTimeImmutable();

        $this->stateWriter->saveLastRun($executedAt->format(DATE_ATOM));
        $this->stateWriter->saveLastKick($kick);
        $this->stateWriter->saveLastOutcome($outcome);
        try {
            $this->executionHistoryStorage->append(
                $executedAt->format('Y-m-d H:i:s'),
                $source,
                $kick,
                $outcome,
                $selection['configured_profile'],
                $selection['effective_profile'],
                $selection['fallback_reason']
            );
        } catch (\Throwable) {
        }

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
     * @return array<int, string>
     */
    private function buildEligibleOutcomeCodes(array $enabledActions): array
    {
        $eligibleOutcomeCodes = [];

        foreach (self::OUTCOME_CODES as $outcomeCode) {
            if (!in_array($outcomeCode, self::ACTION_CODES, true)) {
                $eligibleOutcomeCodes[] = $outcomeCode;
                continue;
            }

            if (($enabledActions[$outcomeCode] ?? false) === true) {
                $eligibleOutcomeCodes[] = $outcomeCode;
            }
        }

        return $eligibleOutcomeCodes;
    }
}
