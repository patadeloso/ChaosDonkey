<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Action;

use DateTimeImmutable;
use DateTimeZone;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use RuntimeException;
use ShaunMcManus\ChaosDonkey\Action\CronQueueHealthSnapshot;
use ShaunMcManus\ChaosDonkey\Model\Probe\ClockInterface;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeOutputFormatter;
use Symfony\Component\Console\Output\BufferedOutput;

#[AllowMockObjectsWithoutExpectations]
class CronQueueHealthSnapshotTest extends TestCase
{
    private ResourceConnection&MockObject $resourceConnection;
    private AdapterInterface&MockObject $adapter;
    private ClockInterface&MockObject $clock;

    protected function setUp(): void
    {
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->adapter = $this->createMock(AdapterInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
    }

    public function testItEmitsFixedOrderFourRowsAndSummaryForHealthyState(): void
    {
        $this->configureResourceConnection($this->adapter);
        $this->configureClockNow(new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')));
        $this->configureTablePresence([
            'cron_schedule' => true,
            'queue' => true,
            'queue_message' => true,
            'queue_message_status' => true,
        ]);

        $this->adapter
            ->expects(self::exactly(3))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls('0', '0', '7');

        $result = $this->runProbe();

        self::assertSame('cron_queue_health_snapshot', $result['instance']->getOutcomeCode());
        self::assertSame('', $result['instance']->getSummary());
        self::assertTrue($result['instance']->isSuccess());

        $lines = $this->splitOutput($result['output']);
        self::assertCount(5, $lines);

        self::assertSame(
            'Probe[cron_queue_health_snapshot] status=ok msg="cron=healthy, queue=healthy, failures_last_60m=0, pending_older_15m=0, activity_last_60m=7"',
            $lines[0]
        );
        self::assertSame(
            'ProbeDetail[cron_queue_health_snapshot] subsystem=cron item=failures_last_60m status=ok value="0"',
            $lines[1]
        );
        self::assertSame(
            'ProbeDetail[cron_queue_health_snapshot] subsystem=cron item=pending_older_15m status=ok value="0"',
            $lines[2]
        );
        self::assertSame(
            'ProbeDetail[cron_queue_health_snapshot] subsystem=queue item=tables_present status=ok value="true"',
            $lines[3]
        );
        self::assertSame(
            'ProbeDetail[cron_queue_health_snapshot] subsystem=queue item=activity_last_60m status=ok value="7"',
            $lines[4]
        );
    }

    public function testItWarnsWhenCronHasRecentFailures(): void
    {
        $this->configureResourceConnection($this->adapter);
        $this->configureClockNow(new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')));
        $this->configureTablePresence([
            'cron_schedule' => true,
            'queue' => true,
            'queue_message' => true,
            'queue_message_status' => true,
        ]);

        $this->adapter
            ->expects(self::exactly(3))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls('3', '0', '9');

        $result = $this->runProbe();

        self::assertStringContainsString('status=warn', $this->splitOutput($result['output'])[0]);
        self::assertStringContainsString('cron=degraded', $this->splitOutput($result['output'])[0]);
        self::assertStringContainsString('subsystem=cron item=failures_last_60m status=warn value="3"', $result['output']);
        self::assertTrue($result['instance']->isSuccess());
    }

    public function testItWarnsWhenCronHasPendingBacklogOlderThanFifteenMinutes(): void
    {
        $this->configureResourceConnection($this->adapter);
        $this->configureClockNow(new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')));
        $this->configureTablePresence([
            'cron_schedule' => true,
            'queue' => true,
            'queue_message' => true,
            'queue_message_status' => true,
        ]);

        $this->adapter
            ->expects(self::exactly(3))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls('0', '12', '9');

        $result = $this->runProbe();

        self::assertStringContainsString('status=warn', $this->splitOutput($result['output'])[0]);
        self::assertStringContainsString('cron=degraded', $this->splitOutput($result['output'])[0]);
        self::assertStringContainsString('subsystem=cron item=pending_older_15m status=warn value="12"', $result['output']);
    }

    public function testItNormalizesCronGetTableNameExceptions(): void
    {
        $this->resourceConnection
            ->expects(self::once())
            ->method('getConnection')
            ->willReturn($this->adapter);

        $this->resourceConnection
            ->expects(self::exactly(4))
            ->method('getTableName')
            ->willReturnCallback(function (string $table): string {
                if ($table === 'cron_schedule') {
                    throw new RuntimeException('cron table metadata failed');
                }

                return $table;
            });

        $this->configureClockNow(new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')));

        $this->configureTablePresence([
            'cron_schedule' => true,
            'queue' => true,
            'queue_message' => true,
            'queue_message_status' => true,
        ]);

        $this->adapter
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn('2');

        $result = $this->runProbe();
        $lines = $this->splitOutput($result['output']);

        self::assertStringContainsString('status=unknown', $lines[0]);
        self::assertStringContainsString('cron=unknown', $lines[0]);
        self::assertStringContainsString('subsystem=cron item=failures_last_60m status=unknown value="n/a"', $result['output']);
        self::assertStringContainsString('subsystem=cron item=pending_older_15m status=unknown value="n/a"', $result['output']);
        self::assertStringContainsString('subsystem=queue item=activity_last_60m status=ok value="2"', $result['output']);
        self::assertFalse($result['instance']->isSuccess());
    }

    public function testItNormalizesQueueIsTableExistsExceptions(): void
    {
        $this->resourceConnection
            ->expects(self::once())
            ->method('getConnection')
            ->willReturn($this->adapter);

        $this->resourceConnection
            ->expects(self::exactly(4))
            ->method('getTableName')
            ->willReturnCallback(static function (string $table): string {
                return $table;
            });

        $this->configureClockNow(new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')));

        $this->adapter
            ->expects(self::exactly(2))
            ->method('isTableExists')
            ->willReturnCallback(function (string $table): bool {
                if ($table === 'queue') {
                    throw new RuntimeException('queue existence check failed');
                }

                return true;
            });

        $this->adapter
            ->expects(self::exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls('3', '1');

        $result = $this->runProbe();
        $lines = $this->splitOutput($result['output']);

        self::assertStringContainsString('status=warn', $lines[0]);
        self::assertStringContainsString('queue=unknown', $lines[0]);
        self::assertStringContainsString('subsystem=queue item=tables_present status=unknown value="n/a"', $result['output']);
        self::assertStringContainsString('subsystem=queue item=activity_last_60m status=unknown value="n/a"', $result['output']);
        self::assertStringContainsString('subsystem=cron item=failures_last_60m status=warn value="3"', $result['output']);
        self::assertStringContainsString('subsystem=cron item=pending_older_15m status=ok value="1"', $result['output']);
        self::assertTrue($result['instance']->isSuccess());
    }

    public function testItWarnsQueueWhenCronWarnsAndQueueHasNoActivity(): void
    {
        $this->configureResourceConnection($this->adapter);
        $this->configureClockNow(new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')));
        $this->configureTablePresence([
            'cron_schedule' => true,
            'queue' => true,
            'queue_message' => true,
            'queue_message_status' => true,
        ]);

        $this->adapter
            ->expects(self::exactly(3))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls('4', '0', '0');

        $result = $this->runProbe();
        $lines = $this->splitOutput($result['output']);

        self::assertStringContainsString('status=warn', $lines[0]);
        self::assertStringContainsString('cron=degraded', $lines[0]);
        self::assertStringContainsString('queue=degraded', $lines[0]);
        self::assertSame(
            'ProbeDetail[cron_queue_health_snapshot] subsystem=cron item=failures_last_60m status=warn value="4"',
            $lines[1]
        );
        self::assertSame(
            'ProbeDetail[cron_queue_health_snapshot] subsystem=cron item=pending_older_15m status=ok value="0"',
            $lines[2]
        );
        self::assertSame(
            'ProbeDetail[cron_queue_health_snapshot] subsystem=queue item=tables_present status=ok value="true"',
            $lines[3]
        );
        self::assertSame(
            'ProbeDetail[cron_queue_health_snapshot] subsystem=queue item=activity_last_60m status=warn value="0"',
            $lines[4]
        );
        self::assertTrue($result['instance']->isSuccess());
    }

    public function testSpecialPrecedenceQueueUnavailableWithCronOkLeavesOverallOk(): void
    {
        $this->configureResourceConnection($this->adapter);
        $this->configureClockNow(new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')));
        $this->configureTablePresence([
            'cron_schedule' => true,
            'queue' => true,
            'queue_message' => true,
            'queue_message_status' => false,
        ]);

        $this->adapter
            ->expects(self::exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls('0', '0');

        $result = $this->runProbe();

        self::assertStringContainsString('status=ok msg="cron=healthy, queue=unavailable', $result['output']);
        self::assertStringContainsString('subsystem=queue item=tables_present status=unavailable value="false"', $result['output']);
        self::assertStringContainsString('subsystem=queue item=activity_last_60m status=unavailable value="n/a"', $result['output']);
        self::assertTrue($result['instance']->isSuccess());
    }

    public function testItUsesInjectedClockForLookbackWindowBoundaries(): void
    {
        $now = new DateTimeImmutable('2026-03-29 10:30:45', new DateTimeZone('UTC'));
        $capturedBinds = [];

        $this->configureResourceConnection($this->adapter);
        $this->configureClockNow($now);
        $this->configureTablePresence([
            'cron_schedule' => true,
            'queue' => true,
            'queue_message' => true,
            'queue_message_status' => true,
        ]);

        $this->adapter
            ->expects(self::exactly(3))
            ->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $bind) use (&$capturedBinds): string {
                $capturedBinds[] = $bind;

                if (str_contains($sql, 'status IN')) {
                    return '1';
                }

                if (str_contains($sql, 'status =')) {
                    return '2';
                }

                return '5';
            });

        $this->runProbe();

        self::assertSame('2026-03-29 09:30:45', $capturedBinds[0]['lookback_60m']);
        self::assertSame('2026-03-29 10:15:45', $capturedBinds[1]['lookback_15m']);
        self::assertSame('2026-03-29 09:30:45', $capturedBinds[2]['lookback_60m']);
    }

    public function testItNormalizesCronQueryFailureToUnknownWithoutDroppingRows(): void
    {
        $this->configureResourceConnection($this->adapter);
        $this->configureClockNow(new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')));
        $this->configureTablePresence([
            'cron_schedule' => true,
            'queue' => true,
            'queue_message' => true,
            'queue_message_status' => true,
        ]);

        $callCount = 0;

        $this->adapter
            ->expects(self::exactly(2))
            ->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $bind) use (&$callCount): string {
                if ($callCount === 0) {
                    $callCount++;

                    throw new RuntimeException('cron query failed');
                }

                return '4';
            });

        $result = $this->runProbe();
        $lines = $this->splitOutput($result['output']);

        self::assertStringContainsString('status=unknown', $lines[0]);
        self::assertStringContainsString('cron=unknown', $lines[0]);
        self::assertStringContainsString('subsystem=cron item=failures_last_60m status=unknown value="n/a"', $result['output']);
        self::assertStringContainsString('subsystem=cron item=pending_older_15m status=unknown value="n/a"', $result['output']);
        self::assertStringContainsString('subsystem=queue item=activity_last_60m status=ok value="4"', $result['output']);
        self::assertFalse($result['instance']->isSuccess());
    }

    public function testItNormalizesQueueQueryFailureToUnknownWithoutDroppingRows(): void
    {
        $this->configureResourceConnection($this->adapter);
        $this->configureClockNow(new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')));
        $this->configureTablePresence([
            'cron_schedule' => true,
            'queue' => true,
            'queue_message' => true,
            'queue_message_status' => true,
        ]);

        $this->adapter
            ->expects(self::exactly(3))
            ->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $bind): string {
                if (str_contains($sql, 'status IN')) {
                    return '0';
                }

                if (str_contains($sql, 'status =')) {
                    return '0';
                }

                throw new RuntimeException('queue query failed');
            });

        $result = $this->runProbe();
        $lines = $this->splitOutput($result['output']);

        self::assertStringContainsString('status=unknown', $lines[0]);
        self::assertStringContainsString('queue=unknown', $lines[0]);
        self::assertStringContainsString('subsystem=queue item=activity_last_60m status=unknown value="n/a"', $result['output']);
        self::assertFalse($result['instance']->isSuccess());
    }

    public function testItReturnsUnknownWhenDbConnectionCannotBeObtained(): void
    {
        $this->resourceConnection
            ->expects(self::once())
            ->method('getConnection')
            ->willThrowException(new RuntimeException('db unavailable'));

        $result = $this->runProbe();

        self::assertStringContainsString('status=unknown', $this->splitOutput($result['output'])[0]);
        self::assertStringContainsString('cron=unknown, queue=unknown, failures_last_60m=n/a, pending_older_15m=n/a, activity_last_60m=n/a', $this->splitOutput($result['output'])[0]);
        self::assertStringContainsString('subsystem=cron item=failures_last_60m status=unknown value="n/a"', $result['output']);
        self::assertStringContainsString('subsystem=queue item=tables_present status=unknown value="n/a"', $result['output']);
        self::assertStringContainsString('subsystem=queue item=activity_last_60m status=unknown value="n/a"', $result['output']);
        self::assertFalse($result['instance']->isSuccess());
    }

    public function testOutputLinesAreBoundedToSummaryPlusAtMostFiveDetails(): void
    {
        $this->configureResourceConnection($this->adapter);
        $this->configureClockNow(new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')));
        $this->configureTablePresence([
            'cron_schedule' => true,
            'queue' => true,
            'queue_message' => true,
            'queue_message_status' => true,
        ]);

        $this->adapter
            ->expects(self::exactly(3))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls('2', '11', '14');

        $result = $this->runProbe();

        $lines = $this->splitOutput($result['output']);
        self::assertLessThanOrEqual(6, count($lines));
    }

    public function testCanonicalProbeEnvelopeFormattingForWarningsAndFailures(): void
    {
        $this->configureResourceConnection($this->adapter);
        $this->configureClockNow(new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')));
        $this->configureTablePresence([
            'cron_schedule' => true,
            'queue' => false,
            'queue_message' => false,
            'queue_message_status' => false,
        ]);

        $this->adapter
            ->expects(self::exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls('1', '12');

        $result = $this->runProbe();

        $resultLines = $this->splitOutput($result['output']);
        self::assertSame(
            'Probe[cron_queue_health_snapshot] status=warn msg="cron=degraded, queue=unavailable, failures_last_60m=1, pending_older_15m=12, activity_last_60m=n/a"',
            $resultLines[0]
        );
        self::assertSame(
            'ProbeDetail[cron_queue_health_snapshot] subsystem=cron item=failures_last_60m status=warn value="1"',
            $resultLines[1]
        );
        self::assertSame(
            'ProbeDetail[cron_queue_health_snapshot] subsystem=cron item=pending_older_15m status=warn value="12"',
            $resultLines[2]
        );
        self::assertSame(
            'ProbeDetail[cron_queue_health_snapshot] subsystem=queue item=tables_present status=unavailable value="false"',
            $resultLines[3]
        );
        self::assertSame(
            'ProbeDetail[cron_queue_health_snapshot] subsystem=queue item=activity_last_60m status=unavailable value="n/a"',
            $resultLines[4]
        );
    }

    private function configureResourceConnection(AdapterInterface $adapter): void
    {
        $this->resourceConnection
            ->expects(self::once())
            ->method('getConnection')
            ->willReturn($adapter);

        $this->resourceConnection
            ->method('getTableName')
            ->willReturnCallback(static function (string $table): string {
                return $table;
            });
    }

    private function configureClockNow(DateTimeImmutable $now): void
    {
        $this->clock
            ->method('nowUtc')
            ->willReturn($now);
    }

    private function configureTablePresence(array $presence): void
    {
        $this->adapter
            ->method('isTableExists')
            ->willReturnCallback(static function (string $table) use ($presence): bool {
                return $presence[$table] ?? false;
            });
    }

    private function runProbe(): array
    {
        $output = new BufferedOutput();
        $action = new CronQueueHealthSnapshot(
            $this->resourceConnection,
            $this->clock,
            new ProbeOutputFormatter()
        );

        $result = $action->execute($output);

        return [
            'output' => trim($output->fetch()),
            'instance' => $result,
        ];
    }

    private function splitOutput(string $output): array
    {
        if ($output === '') {
            return [];
        }

        return preg_split('/\r\n|\r|\n/', $output);
    }
}
