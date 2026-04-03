<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Cron;

use Psr\Log\LoggerInterface;
use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\KickExecutor;

class ChaosDonkeyKickCron
{
    public function __construct(
        private Config $config,
        private KickExecutor $kickExecutor,
        private LoggerInterface $logger
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

        $cronExpression = $this->config->getCronExpression();
        if (!$this->isValidCronExpression($cronExpression)) {
            $this->logMessage('Skipping ChaosDonkey cron because cron_expression is invalid.');

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

        $result = $this->kickExecutor->execute('cron');

        foreach ($result['messages'] as $message) {
            if (str_starts_with($message, 'Probe[') || str_starts_with($message, 'ProbeDetail[')) {
                $this->logMessage($message);
            }
        }

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

    protected function isValidCronExpression(?string $cronExpression): bool
    {
        if ($cronExpression === null) {
            return false;
        }

        $fields = preg_split('/\s+/', trim($cronExpression));
        if (!is_array($fields) || count($fields) !== 5) {
            return false;
        }

        foreach ($fields as $field) {
            if ($field === '' || preg_match('/^[\d*\/,\-]+$/', $field) !== 1) {
                return false;
            }
        }

        return true;
    }

    protected function logMessage(string $message): void
    {
        $this->logger->info('[ChaosDonkey] ' . $message);
    }
}
