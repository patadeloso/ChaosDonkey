<?php
declare(strict_types = 1);

namespace ShaunMcManus\ChaosDonkey\Console\Command;

use ShaunMcManus\ChaosDonkey\Model\Config;
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
        $configuredProfile = $this->config->getExecutionProfile();
        $effectiveProfile = $this->config->getEffectiveExecutionProfile();
        $fallbackReason = $this->config->getExecutionProfileFallbackReason();

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

        $output->writeln('');
        $output->writeln('Configured Action/Probe Toggles');

        foreach (self::ACTION_LABELS as $actionCode => $label) {
            $output->writeln($label . ': ' . ($this->config->isActionEnabled($actionCode) ? 'Enabled' : 'Disabled'));
        }

        return Command::SUCCESS;
    }
}
