<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Model\ExecutionHistoryStorage;

class ExecutionHistoryStorageTest extends TestCase
{
    private ResourceConnection&MockObject $resourceConnection;

    private AdapterInterface&MockObject $connection;

    protected function setUp(): void
    {
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
    }

    public function testItAppendsExecutionHistoryRowsToModuleTable(): void
    {
        $this->resourceConnection
            ->expects(self::once())
            ->method('getTableName')
            ->with('shaunmcmanus_chaosdonkey_execution_history')
            ->willReturn('prefix_shaunmcmanus_chaosdonkey_execution_history');

        $this->resourceConnection
            ->expects(self::once())
            ->method('getConnection')
            ->willReturn($this->connection);

        $this->connection
            ->expects(self::once())
            ->method('insert')
            ->with('prefix_shaunmcmanus_chaosdonkey_execution_history', [
                'executed_at' => '2026-04-02 10:00:00',
                'source' => 'cli',
                'kick' => 20,
                'outcome' => 'critical_success',
                'configured_profile' => 'balanced',
                'effective_profile' => 'balanced',
                'fallback_reason' => null,
            ]);

        $storage = new ExecutionHistoryStorage($this->resourceConnection);

        $storage->append(
            '2026-04-02 10:00:00',
            'cli',
            20,
            'critical_success',
            'balanced',
            'balanced',
            null
        );
    }

    public function testItReturnsRecentExecutionHistoryInDescendingOrder(): void
    {
        $expectedRows = [
            [
                'history_id' => 2,
                'executed_at' => '2026-04-02 10:05:00',
                'source' => 'cron',
                'kick' => 80,
                'outcome' => 'cache_flush',
                'configured_profile' => 'chaos',
                'effective_profile' => 'chaos',
                'fallback_reason' => null,
            ],
        ];

        $this->resourceConnection
            ->expects(self::once())
            ->method('getTableName')
            ->with('shaunmcmanus_chaosdonkey_execution_history')
            ->willReturn('prefix_shaunmcmanus_chaosdonkey_execution_history');

        $this->resourceConnection
            ->expects(self::once())
            ->method('getConnection')
            ->willReturn($this->connection);

        $this->connection
            ->expects(self::once())
            ->method('fetchAll')
            ->with(
                self::callback(static function (string $sql): bool {
                    return str_contains($sql, 'history_id, executed_at, source, kick, outcome, configured_profile, effective_profile, fallback_reason')
                        && str_contains($sql, 'prefix_shaunmcmanus_chaosdonkey_execution_history')
                        && str_contains($sql, 'ORDER BY history_id DESC')
                        && str_contains($sql, 'LIMIT 5');
                }),
                []
            )
            ->willReturn($expectedRows);

        $storage = new ExecutionHistoryStorage($this->resourceConnection);

        self::assertSame($expectedRows, $storage->getRecent(5));
    }
}
