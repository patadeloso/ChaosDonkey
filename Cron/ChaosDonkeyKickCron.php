<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Cron;

use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\KickExecutor;

class ChaosDonkeyKickCron
{
    public function __construct(
        private Config $config,
        private KickExecutor $kickExecutor
    ) {
    }

    public function execute(): void
    {
        $this->logMessage('ChaosDonkey cron started.');

        if (!$this->config->isEnabled()) {
            $this->logMessage('Skipping ChaosDonkey cron because the module is disabled.');

            return;
        }

        if (!$this->config->isCronEnabled()) {
            $this->logMessage('Skipping ChaosDonkey cron because cron execution is disabled.');

            return;
        }

        $allowedHoursRaw = $this->config->getCronAllowedHoursRaw();
        $allowedHours = $this->config->getCronAllowedHours();

        if ($allowedHoursRaw !== null && $allowedHours === []) {
            $this->logMessage('Skipping ChaosDonkey cron because cron_allowed_hours is invalid.');

            return;
        }

        $currentHour = $this->getCurrentHour();
        if ($allowedHours !== [] && !in_array($currentHour, $allowedHours, true)) {
            $this->logMessage(sprintf(
                'Skipping ChaosDonkey cron because current hour %d is not in the allowed window.',
                $currentHour
            ));

            return;
        }

        $this->logMessage(sprintf('Executing ChaosDonkey cron at hour %d.', $currentHour));

        $result = $this->kickExecutor->execute();

        $this->logMessage(sprintf(
            'ChaosDonkey cron completed with kick %d and outcome %s.',
            $result['kick'],
            $result['outcome']
        ));
    }

    protected function getCurrentHour(): int
    {
        return (int) (new \DateTimeImmutable())->format('G');
    }

    protected function logMessage(string $message): void
    {
        error_log('[ChaosDonkey] ' . $message);
    }
}
