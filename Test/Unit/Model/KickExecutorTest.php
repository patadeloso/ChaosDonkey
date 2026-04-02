<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Api\ChaosActionInterface;
use ShaunMcManus\ChaosDonkey\Model\ActionPool;
use ShaunMcManus\ChaosDonkey\Model\ChaosActionResult;
use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\KickExecutor;
use ShaunMcManus\ChaosDonkey\Model\KickRoller;
use ShaunMcManus\ChaosDonkey\Model\RollOutcomeResolver;
use ShaunMcManus\ChaosDonkey\Model\StateWriter;
use Symfony\Component\Console\Output\BufferedOutput;

class KickExecutorTest extends TestCase
{
    private Config&MockObject $config;
    private ActionPool&MockObject $actionPool;
    private RollOutcomeResolver&MockObject $resolver;
    private StateWriter&MockObject $stateWriter;
    private KickRoller&MockObject $kickRoller;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->actionPool = $this->createMock(ActionPool::class);
        $this->resolver = $this->createMock(RollOutcomeResolver::class);
        $this->stateWriter = $this->createMock(StateWriter::class);
        $this->kickRoller = $this->createMock(KickRoller::class);
    }

    public function testItExecutesMappedActionAndCapturesActionOutput(): void
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
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(3);
        $this->resolver->expects(self::once())->method('resolve')->with(3)->willReturn('cache_flush');
        $this->actionPool->expects(self::once())->method('get')->with('cache_flush')->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun')
            ->with(self::callback(static function (string $timestamp): bool {
                $parsed = \DateTimeImmutable::createFromFormat(DATE_ATOM, $timestamp);

                return $parsed !== false && $parsed->format(DATE_ATOM) === $timestamp;
            }));
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(3);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('cache_flush');

        $executor = new KickExecutor($this->config, $this->actionPool, $this->resolver, $this->stateWriter, $this->kickRoller);
        $result = $executor->execute();

        self::assertSame(3, $result['kick']);
        self::assertSame('cache_flush', $result['outcome']);
        self::assertSame([
            'ChaosDonkeyKick kicks your Magento. You rolled a 3',
            'Cache flush started',
            'Cache flush completed',
        ], $result['messages']);
    }

    public function testItRerollsDisabledProbeOutcomeAndPersistsFinalOutcome(): void
    {
        $action = $this->createStub(ChaosActionInterface::class);
        $action->method('execute')->willReturn(new ChaosActionResult('cache_flush', 'Cache flush completed'));

        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnMap([
                ['reindex_all', false],
                ['cache_flush', true],
                ['graphql_pipeline_stress', true],
                ['indexer_status_snapshot', false],
                ['cache_backend_health_snapshot', true],
                ['cron_queue_health_snapshot', true],
            ]);
        $this->kickRoller->expects(self::exactly(2))
            ->method('rollD20')
            ->willReturnOnConsecutiveCalls(5, 3);
        $resolvedRolls = [];
        $this->resolver->expects(self::exactly(2))
            ->method('resolve')
            ->willReturnCallback(static function (int $kick) use (&$resolvedRolls): string {
                $resolvedRolls[] = $kick;

                return match ($kick) {
                    5 => 'indexer_status_snapshot',
                    3 => 'cache_flush',
                };
            });
        $this->actionPool->expects(self::once())->method('get')->with('cache_flush')->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(3);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('cache_flush');

        $executor = new KickExecutor($this->config, $this->actionPool, $this->resolver, $this->stateWriter, $this->kickRoller);
        $result = $executor->execute();

        self::assertSame([5, 3], $resolvedRolls);
        self::assertSame(3, $result['kick']);
        self::assertSame('cache_flush', $result['outcome']);
        self::assertSame([
            'ChaosDonkeyKick kicks your Magento. You rolled a 3',
            'Cache flush completed',
        ], $result['messages']);
    }

    public function testItWarnsOnceWhenAllActionsDisabledAndExecutesNonActionOutcome(): void
    {
        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturn(false);
        $this->kickRoller->expects(self::exactly(4))
            ->method('rollD20')
            ->willReturnOnConsecutiveCalls(2, 3, 4, 1);
        $resolvedRolls = [];
        $this->resolver->expects(self::exactly(4))
            ->method('resolve')
            ->willReturnCallback(static function (int $kick) use (&$resolvedRolls): string {
                $resolvedRolls[] = $kick;

                return match ($kick) {
                    1 => 'critical_failure',
                    2 => 'reindex_all',
                    3 => 'cache_flush',
                    4 => 'graphql_pipeline_stress',
                };
            });
        $this->actionPool->expects(self::once())->method('get')->with('critical_failure')->willReturn(null);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(1);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('critical_failure');

        $executor = new KickExecutor($this->config, $this->actionPool, $this->resolver, $this->stateWriter, $this->kickRoller);
        $result = $executor->execute();

        self::assertSame([2, 3, 4, 1], $resolvedRolls);
        self::assertSame(1, $result['kick']);
        self::assertSame('critical_failure', $result['outcome']);
        self::assertSame([
            'All configured chaos actions/probes are disabled. Rolling non-action outcomes only.',
            'ChaosDonkeyKick kicks your Magento. You rolled a 1',
            'Critical Failure! Better check all of your donkeys.',
        ], $result['messages']);
    }

    public function testItFallsBackToNappingAfterMaxAttemptsWhenProbesRemainDisabled(): void
    {
        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnMap([
                ['reindex_all', false],
                ['cache_flush', true],
                ['graphql_pipeline_stress', false],
                ['indexer_status_snapshot', false],
                ['cache_backend_health_snapshot', false],
                ['cron_queue_health_snapshot', false],
            ]);
        $this->kickRoller->expects(self::exactly(20))->method('rollD20')->willReturn(5);
        $this->resolver->expects(self::exactly(20))->method('resolve')->with(5)->willReturn('indexer_status_snapshot');
        $this->actionPool->expects(self::once())->method('get')->with('napping')->willReturn(null);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(5);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('napping');

        $executor = new KickExecutor($this->config, $this->actionPool, $this->resolver, $this->stateWriter, $this->kickRoller);
        $result = $executor->execute();

        self::assertSame(5, $result['kick']);
        self::assertSame('napping', $result['outcome']);
        self::assertSame([
            'Max reroll attempts reached. Falling back to napping.',
            'ChaosDonkeyKick kicks your Magento. You rolled a 5',
            'The donkeys are napping',
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
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(6);
        $this->resolver->expects(self::once())->method('resolve')->with(6)->willReturn('cache_backend_health_snapshot');
        $this->actionPool->expects(self::once())->method('get')->with('cache_backend_health_snapshot')->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(6);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('cache_backend_health_snapshot');

        $executor = new KickExecutor($this->config, $this->actionPool, $this->resolver, $this->stateWriter, $this->kickRoller);
        $result = $executor->execute();

        self::assertSame(6, $result['kick']);
        self::assertSame('cache_backend_health_snapshot', $result['outcome']);
        self::assertSame([
            'ChaosDonkeyKick kicks your Magento. You rolled a 6',
            'Probe output',
        ], $result['messages']);
    }
}
