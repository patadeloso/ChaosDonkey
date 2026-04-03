<?php
declare(strict_types = 1);

namespace ShaunMcManus\ChaosDonkey\Console\Command;

use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\ExecutionHistoryStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChaosDonkeyStatus extends Command
{
    private const ACTION_LABELS = [
        'reindex_all' => 'Reindex all',
        'cache_flush' => 'Cache flush',
        'graphql_pipeline_stress' => 'GraphQL pipeline stress',
        'indexer_status_snapshot' => 'Indexer status snapshot',
        'cache_backend_health_snapshot' => 'Cache backend health snapshot',
        'cron_queue_health_snapshot' => 'Cron queue health snapshot',
    ];

    private const RECENT_HISTORY_LIMIT = 5;

    private Config $config;

    private ExecutionHistoryStorage $executionHistoryStorage;

    public function __construct(Config $config, ExecutionHistoryStorage $executionHistoryStorage)
    {
        parent::__construct();
        $this->config = $config;
        $this->executionHistoryStorage = $executionHistoryStorage;
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
        $configuredProfile = $this->config->getExecutionProfile();
        $effectiveProfile = $this->config->getEffectiveExecutionProfile();
        $fallbackReason = $this->config->getExecutionProfileFallbackReason();
        $cronEnabled = $this->config->isCronEnabled();
        $recentHistoryUnavailable = false;
        $latestCliExecution = null;
        $latestCronExecution = null;

        try {
            $latestCliExecution = $this->executionHistoryStorage->getLatestForSource('cli');
            $latestCronExecution = $this->executionHistoryStorage->getLatestForSource('cron');
            $recentHistory = $this->executionHistoryStorage->getRecent(self::RECENT_HISTORY_LIMIT);
        } catch (\Throwable) {
            $recentHistoryUnavailable = true;
            $recentHistory = [];
        }

        $output->writeln('ChaosDonkey Status');
        $output->writeln('Enabled: ' . $enabled);
        $output->writeln('Last run: ' . $lastRun);
        $output->writeln('Last kick: ' . $lastKick);
        $output->writeln('Last outcome: ' . $lastOutcome);
        $output->writeln('Configured profile: ' . $configuredProfile);
        $output->writeln('Effective profile: ' . $effectiveProfile);

        if ($fallbackReason !== null) {
            $output->writeln('Fallback reason: ' . $fallbackReason);

            if ($fallbackReason === 'invalid_fallback_profile') {
                $output->writeln('Fallback mode: emergency_legacy_balanced_table');
            }
        }

        $output->writeln('Last CLI execution: ' . $this->formatLatestExecution($latestCliExecution, $recentHistoryUnavailable));
        $output->writeln('Last cron execution: ' . $this->formatLatestExecution($latestCronExecution, $recentHistoryUnavailable));

        if (!$recentHistoryUnavailable && $cronEnabled && $latestCronExecution === null) {
            $output->writeln('Cron notice: Cron is enabled but no cron execution has been recorded yet.');
        }

        $output->writeln('');
        $output->writeln('Recent execution history');

        if ($recentHistoryUnavailable) {
            $output->writeln('History unavailable.');
        } elseif ($recentHistory === []) {
            $output->writeln('None recorded.');
        } else {
            foreach ($recentHistory as $historyRow) {
                $output->writeln($this->formatRecentHistoryRow($historyRow));
            }
        }

        $output->writeln('');
        $output->writeln('Configured Action/Probe Toggles');

        foreach (self::ACTION_LABELS as $actionCode => $label) {
            $output->writeln($label . ': ' . ($this->config->isActionEnabled($actionCode) ? 'Enabled' : 'Disabled'));
        }

        return Command::SUCCESS;
    }

    private function formatRecentHistoryRow(array $historyRow): string
    {
        return '- ' . (string) $historyRow['executed_at'] . ' | ' . (string) $historyRow['source'] . ' | ' . $this->formatExecutionSummary($historyRow);
    }

    private function formatLatestExecution(?array $historyRow, bool $recentHistoryUnavailable): string
    {
        if ($recentHistoryUnavailable) {
            return 'History unavailable.';
        }

        if ($historyRow === null) {
            return 'Never recorded.';
        }

        return (string) $historyRow['executed_at'] . ' | ' . $this->formatExecutionSummary($historyRow);
    }

    private function formatExecutionSummary(array $historyRow): string
    {
        $profileSummary = (string) $historyRow['configured_profile'];

        if ((string) $historyRow['configured_profile'] !== (string) $historyRow['effective_profile']) {
            $profileSummary .= ' -> ' . (string) $historyRow['effective_profile'];
        }

        $line = sprintf(
            'kick %s | %s | profile %s',
            (string) $historyRow['kick'],
            (string) $historyRow['outcome'],
            $profileSummary
        );

        if ($historyRow['fallback_reason'] !== null && $historyRow['fallback_reason'] !== '') {
            $line .= ' | fallback ' . (string) $historyRow['fallback_reason'];
        }

        return $line;
    }
}
