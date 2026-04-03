<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ShaunMcManus\ChaosDonkey\Api\ChaosActionInterface;
use ShaunMcManus\ChaosDonkey\Model\ActionPool;
use ShaunMcManus\ChaosDonkey\Model\ChaosActionResult;
use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\ExecutionHistoryStorage;
use ShaunMcManus\ChaosDonkey\Model\KickExecutor;
use ShaunMcManus\ChaosDonkey\Model\KickRoller;
use ShaunMcManus\ChaosDonkey\Model\Profile\ProfiledRollSelector;
use ShaunMcManus\ChaosDonkey\Model\StateWriter;
use Symfony\Component\Console\Output\BufferedOutput;

class KickExecutorTest extends TestCase
{
    private Config&MockObject $config;
    private ActionPool&MockObject $actionPool;
    private ExecutionHistoryStorage&MockObject $executionHistoryStorage;
    private ProfiledRollSelector $profiledRollSelector;
    private StateWriter&MockObject $stateWriter;
    private KickRoller&MockObject $kickRoller;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->actionPool = $this->createMock(ActionPool::class);
        $this->executionHistoryStorage = $this->createMock(ExecutionHistoryStorage::class);
        $this->profiledRollSelector = new ProfiledRollSelector();
        $this->stateWriter = $this->createMock(StateWriter::class);
        $this->kickRoller = $this->createMock(KickRoller::class);
    }

    public function testItExecutesSelectedActionAndCapturesActionOutput(): void
    {
        $action = $this->createMock(ChaosActionInterface::class);
        $action->expects(self::once())
            ->method('execute')
            ->willReturnCallback(static function (BufferedOutput $output): ChaosActionResult {
                $output->writeln('Cache flush started');

                return new ChaosActionResult('cache_flush', 'Cache flush completed');
            });

        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnMap([
                ['reindex_all', true],
                ['cache_flush', true],
                ['graphql_pipeline_stress', true],
                ['indexer_status_snapshot', true],
                ['cache_backend_health_snapshot', true],
                ['cron_queue_health_snapshot', true],
            ]);
        $this->config->expects(self::once())->method('getExecutionProfile')->willReturn('balanced');
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(3);
        $this->actionPool->expects(self::once())
            ->method('get')
            ->with(self::callback(static function (string $selectedOutcome): bool {
                return $selectedOutcome !== '';
            }))
            ->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun')
            ->with(self::callback(static function (string $timestamp): bool {
                $parsed = \DateTimeImmutable::createFromFormat(DATE_ATOM, $timestamp);

                return $parsed !== false && $parsed->format(DATE_ATOM) === $timestamp;
            }));
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(3);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with(self::callback('is_string'));
        $this->executionHistoryStorage->expects(self::once())
            ->method('append')
            ->with(
                self::callback(static function (string $executedAt): bool {
                    $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $executedAt);

                    return $parsed !== false && $parsed->format('Y-m-d H:i:s') === $executedAt;
                }),
                'cli',
                3,
                self::callback('is_string'),
                'balanced',
                'balanced',
                null
            );

        $executor = new KickExecutor(
            $this->config,
            $this->actionPool,
            $this->profiledRollSelector,
            $this->stateWriter,
            $this->kickRoller,
            $this->executionHistoryStorage
        );
        $result = $executor->execute('cli');

        self::assertSame(3, $result['kick']);
        self::assertIsString($result['outcome']);
        self::assertSame([
            'ChaosDonkeyKick kicks your Magento. You rolled a 3',
            'Cache flush started',
            'Cache flush completed',
        ], $result['messages']);
    }

    public function testItExcludesDisabledOutcomesBeforeSelectionAndExecutesSelectedAction(): void
    {
        $action = $this->createMock(ChaosActionInterface::class);
        $action->expects(self::once())
            ->method('execute')
            ->willReturnCallback(static function (BufferedOutput $output): ChaosActionResult {
                $output->writeln('GraphQL stress started');

                return new ChaosActionResult('graphql_pipeline_stress', 'GraphQL stress completed');
            });

        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnMap([
                ['reindex_all', true],
                ['cache_flush', false],
                ['graphql_pipeline_stress', true],
                ['indexer_status_snapshot', true],
                ['cache_backend_health_snapshot', true],
                ['cron_queue_health_snapshot', true],
            ]);
        $this->config->expects(self::once())->method('getExecutionProfile')->willReturn('balanced');
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(3);
        $this->actionPool->expects(self::once())->method('get')->with('graphql_pipeline_stress')->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(self::callback(static function (int $kick): bool {
            return $kick === 3 && $kick >= 1 && $kick <= 20;
        }));
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('graphql_pipeline_stress');
        $this->executionHistoryStorage->expects(self::once())
            ->method('append')
            ->with(
                self::anything(),
                'cli',
                3,
                'graphql_pipeline_stress',
                'balanced',
                'balanced',
                null
            );

        $executor = new KickExecutor(
            $this->config,
            $this->actionPool,
            $this->profiledRollSelector,
            $this->stateWriter,
            $this->kickRoller,
            $this->executionHistoryStorage
        );
        $result = $executor->execute('cli');

        self::assertSame(3, $result['kick']);
        self::assertSame('graphql_pipeline_stress', $result['outcome']);
        self::assertSame([
            'ChaosDonkeyKick kicks your Magento. You rolled a 3',
            'GraphQL stress started',
            'GraphQL stress completed',
        ], $result['messages']);
    }

    public function testItStillReturnsCliResultWhenHistoryWriteFails(): void
    {
        $action = $this->createMock(ChaosActionInterface::class);
        $action->expects(self::once())
            ->method('execute')
            ->willReturnCallback(static function (BufferedOutput $output): ChaosActionResult {
                $output->writeln('Cache flush started');

                return new ChaosActionResult('cache_flush', 'Cache flush completed');
            });

        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnMap([
                ['reindex_all', true],
                ['cache_flush', true],
                ['graphql_pipeline_stress', true],
                ['indexer_status_snapshot', true],
                ['cache_backend_health_snapshot', true],
                ['cron_queue_health_snapshot', true],
            ]);
        $this->config->expects(self::once())->method('getExecutionProfile')->willReturn('balanced');
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(3);
        $this->actionPool->expects(self::once())->method('get')->with('cache_flush')->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(3);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('cache_flush');
        $this->executionHistoryStorage->expects(self::once())
            ->method('append')
            ->with(
                self::anything(),
                'cli',
                3,
                'cache_flush',
                'balanced',
                'balanced',
                null
            )
            ->willThrowException(new RuntimeException('history insert failed'));

        $executor = new KickExecutor(
            $this->config,
            $this->actionPool,
            $this->profiledRollSelector,
            $this->stateWriter,
            $this->kickRoller,
            $this->executionHistoryStorage
        );

        $result = $executor->execute('cli');

        self::assertSame(3, $result['kick']);
        self::assertSame('cache_flush', $result['outcome']);
        self::assertSame([
            'ChaosDonkeyKick kicks your Magento. You rolled a 3',
            'Cache flush started',
            'Cache flush completed',
        ], $result['messages']);
    }

    public function testItStillReturnsCronResultWhenHistoryWriteFails(): void
    {
        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnMap([
                ['reindex_all', true],
                ['cache_flush', false],
                ['graphql_pipeline_stress', false],
                ['indexer_status_snapshot', false],
                ['cache_backend_health_snapshot', true],
                ['cron_queue_health_snapshot', false],
            ]);
        $this->config->expects(self::once())->method('getExecutionProfile')->willReturn('balanced');
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(4);
        $this->actionPool->expects(self::once())->method('get')->with('cache_backend_health_snapshot')->willReturn(null);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(4);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('cache_backend_health_snapshot');
        $this->executionHistoryStorage->expects(self::once())
            ->method('append')
            ->with(
                self::anything(),
                'cron',
                4,
                'cache_backend_health_snapshot',
                'balanced',
                'balanced',
                null
            )
            ->willThrowException(new RuntimeException('history insert failed'));

        $executor = new KickExecutor(
            $this->config,
            $this->actionPool,
            $this->profiledRollSelector,
            $this->stateWriter,
            $this->kickRoller,
            $this->executionHistoryStorage
        );

        $result = $executor->execute('cron');

        self::assertSame(4, $result['kick']);
        self::assertSame('cache_backend_health_snapshot', $result['outcome']);
        self::assertSame([
            'ChaosDonkeyKick kicks your Magento. You rolled a 4',
            'Unknown chaos outcome. The donkeys stare suspiciously.',
        ], $result['messages']);
    }

    public function testItWarnsOnceWhenAllActionsDisabledAndExecutesNonActionOutcome(): void
    {
        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturn(false);
        $this->config->expects(self::once())->method('getExecutionProfile')->willReturn('custom_profile_that_falls_back');
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(2);
        $this->actionPool->expects(self::once())->method('get')->with('critical_failure')->willReturn(null);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(self::callback(static function (int $kick): bool {
            return $kick === 2 && $kick >= 1 && $kick <= 20;
        }));
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('critical_failure');
        $this->executionHistoryStorage->expects(self::once())
            ->method('append')
            ->with(
                self::anything(),
                'cli',
                2,
                'critical_failure',
                'custom_profile_that_falls_back',
                'balanced',
                'invalid_configured_profile'
            );

        $executor = new KickExecutor(
            $this->config,
            $this->actionPool,
            $this->profiledRollSelector,
            $this->stateWriter,
            $this->kickRoller,
            $this->executionHistoryStorage
        );
        $result = $executor->execute('cli');

        self::assertSame(2, $result['kick']);
        self::assertSame('critical_failure', $result['outcome']);
        self::assertSame([
            'All configured chaos actions/probes are disabled. Rolling non-action outcomes only.',
            'ChaosDonkeyKick kicks your Magento. You rolled a 2',
            'Critical Failure! Better check all of your donkeys.',
        ], $result['messages']);
    }

    public function testItUsesConfiguredExecutionProfileInsteadOfLegacyStaticResolverMapping(): void
    {
        $action = $this->createMock(ChaosActionInterface::class);
        $action->expects(self::once())
            ->method('execute')
            ->willReturn(new ChaosActionResult('cache_flush', 'Cache flush completed'));

        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnMap([
                ['reindex_all', true],
                ['cache_flush', true],
                ['graphql_pipeline_stress', true],
                ['indexer_status_snapshot', true],
                ['cache_backend_health_snapshot', true],
                ['cron_queue_health_snapshot', true],
            ]);
        $this->config->expects(self::once())->method('getExecutionProfile')->willReturn('all_gas_no_brakes');
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(8);
        $this->actionPool->expects(self::once())->method('get')->with('cache_flush')->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(self::callback(static function (int $kick): bool {
            return $kick === 8 && $kick >= 1 && $kick <= 20;
        }));
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('cache_flush');
        $this->executionHistoryStorage->expects(self::once())
            ->method('append')
            ->with(
                self::anything(),
                'cli',
                8,
                'cache_flush',
                'all_gas_no_brakes',
                'all_gas_no_brakes',
                null
            );

        $executor = new KickExecutor(
            $this->config,
            $this->actionPool,
            $this->profiledRollSelector,
            $this->stateWriter,
            $this->kickRoller,
            $this->executionHistoryStorage
        );
        $result = $executor->execute('cli');

        self::assertSame(8, $result['kick']);
        self::assertSame('cache_flush', $result['outcome']);
        self::assertSame([
            'ChaosDonkeyKick kicks your Magento. You rolled a 8',
            'Cache flush completed',
        ], $result['messages']);
    }

    public function testItOmitsEmptyActionSummaryWithoutOutcomeBranching(): void
    {
        $action = $this->createStub(ChaosActionInterface::class);
        $action
            ->method('execute')
            ->willReturnCallback(static function (BufferedOutput $output): ChaosActionResult {
                $output->writeln('Probe output');

                return new ChaosActionResult('cache_backend_health_snapshot', '');
            });

        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnMap([
                ['reindex_all', true],
                ['cache_flush', false],
                ['graphql_pipeline_stress', false],
                ['indexer_status_snapshot', false],
                ['cache_backend_health_snapshot', true],
                ['cron_queue_health_snapshot', false],
            ]);
        $this->config->expects(self::once())->method('getExecutionProfile')->willReturn('balanced');
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(4);
        $this->actionPool->expects(self::once())->method('get')->with('cache_backend_health_snapshot')->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(4);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('cache_backend_health_snapshot');
        $this->executionHistoryStorage->expects(self::once())
            ->method('append')
            ->with(
                self::anything(),
                'cron',
                4,
                'cache_backend_health_snapshot',
                'balanced',
                'balanced',
                null
            );

        $executor = new KickExecutor(
            $this->config,
            $this->actionPool,
            $this->profiledRollSelector,
            $this->stateWriter,
            $this->kickRoller,
            $this->executionHistoryStorage
        );
        $result = $executor->execute('cron');

        self::assertSame(4, $result['kick']);
        self::assertSame('cache_backend_health_snapshot', $result['outcome']);
        self::assertSame([
            'ChaosDonkeyKick kicks your Magento. You rolled a 4',
            'Probe output',
        ], $result['messages']);
    }
}
