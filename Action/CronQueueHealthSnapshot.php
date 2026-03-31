<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Action;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use ShaunMcManus\ChaosDonkey\Api\ChaosActionInterface;
use ShaunMcManus\ChaosDonkey\Model\ChaosActionResult;
use ShaunMcManus\ChaosDonkey\Model\Probe\ClockInterface;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeDetailRow;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeOutputFormatter;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeSnapshot;
use Throwable;
use Symfony\Component\Console\Output\OutputInterface;

class CronQueueHealthSnapshot implements ChaosActionInterface
{
    private const string CODE = 'cron_queue_health_snapshot';

    public function __construct(
        private ResourceConnection $resourceConnection,
        private ClockInterface $clock,
        private ProbeOutputFormatter $probeOutputFormatter
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function execute(OutputInterface $output): ChaosActionResult
    {
        $snapshot = $this->collectSnapshot();

        $output->writeln($this->probeOutputFormatter->formatLines($snapshot));

        return new ChaosActionResult(
            $this->getCode(),
            '',
            [],
            $snapshot->getStatus() !== 'unknown'
        );
    }

    private function collectSnapshot(): ProbeSnapshot
    {
        try {
            /** @var AdapterInterface $adapter */
            $adapter = $this->resourceConnection->getConnection();
        } catch (Throwable $exception) {
            return new ProbeSnapshot(
                $this->getCode(),
                'unknown',
                'cron=unknown, queue=unknown, failures_last_60m=n/a, pending_older_15m=n/a, activity_last_60m=n/a',
                [
                    new ProbeDetailRow('cron', 'failures_last_60m', 'unknown', 'n/a'),
                    new ProbeDetailRow('cron', 'pending_older_15m', 'unknown', 'n/a'),
                    new ProbeDetailRow('queue', 'tables_present', 'unknown', 'n/a'),
                    new ProbeDetailRow('queue', 'activity_last_60m', 'unknown', 'n/a'),
                ],
                true
            );
        }

        $nowUtc = $this->clock->nowUtc();
        $lookback60m = $nowUtc->modify('-60 minutes');
        $lookback15m = $nowUtc->modify('-15 minutes');
        $lookback60mForQuery = $lookback60m->format('Y-m-d H:i:s');
        $lookback15mForQuery = $lookback15m->format('Y-m-d H:i:s');

        $cronSnapshot = $this->collectCronSnapshot(
            $adapter,
            $lookback60mForQuery,
            $lookback15mForQuery
        );

        $queueSnapshot = $this->collectQueueSnapshot(
            $adapter,
            $lookback60mForQuery,
            $cronSnapshot['status']
        );

        $overallStatus = $this->resolveOverallStatus(
            $cronSnapshot['status'],
            $queueSnapshot['status']
        );

        $summary = sprintf(
            'cron=%s, queue=%s, failures_last_60m=%s, pending_older_15m=%s, activity_last_60m=%s',
            $this->statusHeadline($cronSnapshot['status']),
            $this->statusHeadline($queueSnapshot['status']),
            $cronSnapshot['failures_last_60m'],
            $cronSnapshot['pending_older_15m'],
            $queueSnapshot['activity_last_60m']
        );

        return new ProbeSnapshot(
            $this->getCode(),
            $overallStatus,
            $summary,
            [
                new ProbeDetailRow(
                    'cron',
                    'failures_last_60m',
                    $cronSnapshot['status'],
                    $cronSnapshot['failures_last_60m']
                ),
                new ProbeDetailRow(
                    'cron',
                    'pending_older_15m',
                    $cronSnapshot['status'],
                    $cronSnapshot['pending_older_15m']
                ),
                new ProbeDetailRow(
                    'queue',
                    'tables_present',
                    $queueSnapshot['tables_present_status'],
                    $queueSnapshot['tables_present']
                ),
                new ProbeDetailRow(
                    'queue',
                    'activity_last_60m',
                    $queueSnapshot['activity_status'],
                    $queueSnapshot['activity_last_60m']
                ),
            ],
            true
        );
    }

    /**
     * @return array{status: string, failures_last_60m: string, pending_older_15m: string}
     */
    private function collectCronSnapshot(
        AdapterInterface $adapter,
        string $lookback60m,
        string $lookback15m
    ): array {
        $cronTable = $this->resourceConnection->getTableName('cron_schedule');

        try {
            if (!$adapter->isTableExists($cronTable)) {
                return [
                    'status' => 'unknown',
                    'failures_last_60m' => 'n/a',
                    'pending_older_15m' => 'n/a',
                ];
            }

            $failures = $this->fetchCount(
                $adapter,
                sprintf(
                    'SELECT COUNT(*) FROM %s WHERE status IN (\'error\', \'missed\') AND scheduled_at >= :lookback_60m',
                    $cronTable
                ),
                ['lookback_60m' => $lookback60m]
            );

            $pending = $this->fetchCount(
                $adapter,
                sprintf(
                    'SELECT COUNT(*) FROM %s WHERE status = \'pending\' AND scheduled_at < :lookback_15m',
                    $cronTable
                ),
                ['lookback_15m' => $lookback15m]
            );

            return [
                'status' => ($failures > 0 || $pending > 10) ? 'warn' : 'ok',
                'failures_last_60m' => (string) $failures,
                'pending_older_15m' => (string) $pending,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'unknown',
                'failures_last_60m' => 'n/a',
                'pending_older_15m' => 'n/a',
            ];
        }
    }

    /**
     * @return array{
     *     status: string,
     *     tables_present_status: string,
     *     tables_present: string,
     *     activity_status: string,
     *     activity_last_60m: string
     * }
     */
    private function collectQueueSnapshot(
        AdapterInterface $adapter,
        string $lookback60m,
        string $cronStatus
    ): array {
        $queueTable = $this->resourceConnection->getTableName('queue');
        $queueMessageTable = $this->resourceConnection->getTableName('queue_message');
        $queueMessageStatusTable = $this->resourceConnection->getTableName('queue_message_status');

        if (
            !$adapter->isTableExists($queueTable)
            || !$adapter->isTableExists($queueMessageTable)
            || !$adapter->isTableExists($queueMessageStatusTable)
        ) {
            return [
                'status' => 'unavailable',
                'tables_present_status' => 'unavailable',
                'tables_present' => 'false',
                'activity_status' => 'unavailable',
                'activity_last_60m' => 'n/a',
            ];
        }

        try {
            $activityCount = $this->fetchCount(
                $adapter,
                sprintf(
                    'SELECT COUNT(*) FROM %s WHERE updated_at >= :lookback_60m',
                    $queueMessageStatusTable
                ),
                ['lookback_60m' => $lookback60m]
            );

            return [
                'status' => ($activityCount === 0 && $cronStatus === 'warn') ? 'warn' : 'ok',
                'tables_present_status' => 'ok',
                'tables_present' => 'true',
                'activity_status' => ($activityCount === 0 && $cronStatus === 'warn') ? 'warn' : 'ok',
                'activity_last_60m' => (string) $activityCount,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'unknown',
                'tables_present_status' => 'ok',
                'tables_present' => 'true',
                'activity_status' => 'unknown',
                'activity_last_60m' => 'n/a',
            ];
        }
    }

    private function fetchCount(AdapterInterface $adapter, string $sql, array $bind): int
    {
        $value = $adapter->fetchOne($sql, $bind);

        if (is_numeric((string) $value)) {
            return (int) $value;
        }

        return 0;
    }

    private function resolveOverallStatus(string $cronStatus, string $queueStatus): string
    {
        if ($cronStatus === 'warn' || $queueStatus === 'warn') {
            return 'warn';
        }

        if ($cronStatus === 'unknown' || $queueStatus === 'unknown') {
            return 'unknown';
        }

        if ($cronStatus === 'ok' && $queueStatus === 'unavailable') {
            return 'ok';
        }

        return 'ok';
    }

    private function statusHeadline(string $status): string
    {
        return match ($status) {
            'ok' => 'healthy',
            'warn' => 'degraded',
            'unavailable' => 'unavailable',
            default => 'unknown',
        };
    }

}
